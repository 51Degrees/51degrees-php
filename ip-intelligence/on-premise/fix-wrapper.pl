#!/usr/bin/env perl
#
# Fixes the code SWIG 4.5.1 writes into the PHP wrapper that MSVC warns
# about at /W4, and that GCC and Clang warn about at -Wall once config.m4
# defines SWIGUNUSED empty. No SWIG option or typemap reaches this code.
# Both extensions' generate-wrapper.sh use it. Reads the wrapper on standard
# input and writes the fixed wrapper to standard output.
#
# - A static function that nothing calls is removed (C4505,
#   -Wunused-function). That also removes the runtime's only strcpy and
#   strncpy calls (C4996).
# - A "fail" label that nothing jumps to is removed (C4102, -Wunused-label).
# - A wrapper function that does not use return_value casts it to void
#   (C4100).
# - "thisown" is stored as a truth value, not a zend_long cut to int (C4244).
# - The module pointer is stored through intptr_t, as long is 32 bits on
#   64-bit Windows (C4302 and C4311).
# - The runtime's blocks that turn warnings off are removed. These are the
#   GCC pragma around php.h, the C4505 pragma, the CRT and C++ library
#   deprecation defines (C4996) and the Intel compiler pragma. None of them
#   is in effect in either extension's build, and a warning must never be
#   hidden.
#
# Each fix stops the script with an error when it finds nothing to change,
# as that means SWIG's output is not what this script was written for.

use strict;
use warnings;

my @lines = <STDIN>;
my $text = join '', @lines;

# Every macro whose expansion jumps to the fail label.
my %jumps = (SWIG_fail => 1);
my %macros;
while ($text =~ /^#\s*define\s+(\w+)((?:[^\n]*\\\n)*[^\n]*)/mg) {
    $macros{$1} = $2;
}
for (my $added = 1; $added;) {
    $added = 0;
    my $known = join '|', keys %jumps;
    for my $name (keys %macros) {
        next if $jumps{$name};
        if ($macros{$name} =~ /\b(?:$known)\b|\bgoto\s+fail\b/) {
            $jumps{$name} = 1;
            $added = 1;
        }
    }
}
my $jump = join '|', sort keys %jumps;
$jump = qr/\b(?:$jump)\b|\bgoto\s+fail\b/;

# The text without comments, and without the definitions of macros that
# nothing uses, so that neither counts as a call.
sub code {
    my ($s) = @_;
    $s =~ s{/\*.*?\*/}{}gs;
    $s =~ s{//[^\n]*}{}g;
    my %count;
    $count{$_}++ for $s =~ /\b(\w+)\b/g;
    $s =~ s/^#\s*define\s+(\w+)(?:[^\n]*\\\n)*[^\n]*\n/$count{$1} > 1 ? $& : ''/mge;
    return $s;
}

# The index of the line that closes the brace the line at $_[0] opens. A
# function can hold a block whose closing brace is at the start of a line,
# so the first such line is not always the function's end. Braces in string
# and character literals, and in comments that end on their own line, do not
# count.
sub closing {
    my ($i) = @_;
    my $depth = 0;
    for (my $j = $i; $j <= $#lines; $j++) {
        my $line = $lines[$j];
        $line =~ s{"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|/\*.*?\*/|//.*}{}g;
        $depth += () = $line =~ /\{/g;
        $depth -= () = $line =~ /\}/g;
        return $j if $depth == 0 && $line =~ /\}/;
    }
    die "fix-wrapper.pl: a function at line @{[$i + 1]} does not close.\n";
}

# Stops with an error when a fix changed nothing.
sub changed {
    my ($count, $what) = @_;
    die "fix-wrapper.pl: found no $what to fix.\n" if $count == 0;
}

# Removes static functions that nothing calls, until none is left, because
# removing one can leave another without a caller.
my $functions = 0;
my $head =
    qr/^(?:SWIGRUNTIME|SWIGRUNTIMEINLINE|SWIGINTERN|SWIGINTERNINLINE)\b[^;{(]*$/;
for (my $removed = 1; $removed;) {
    $removed = 0;
    my $whole = code(join "", @lines);
    for (my $i = 0; $i < $#lines; $i++) {
        next unless $lines[$i] =~ $head && $lines[$i + 1] =~ /^(\w+)\s*\(/;
        my $name = $1;
        my $end = $i + 1;
        $end++ while $end <= $#lines && $lines[$end] !~ /[{;]\s*$/;
        next if $end > $#lines || $lines[$end] =~ /;\s*$/;
        $end = closing($end);
        my $inside = join '', @lines[$i .. $end];
        my $all = () = $whole =~ /\b\Q$name\E\b/g;
        my $own = () = code($inside) =~ /\b\Q$name\E\b/g;
        next if $all > $own;
        my $start = $i;
        if ($start > 0 && $lines[$start - 1] =~ m{^\s*\*/\s*$}) {
            my $c = $start - 1;
            $c-- while $c > 0 && $lines[$c] !~ m{^\s*/\*};
            $start = $c;
        }
        $end++ if $end < $#lines && $lines[$end + 1] =~ /^\s*$/;
        splice @lines, $start, $end - $start + 1;
        $functions++;
        $removed = 1;
        last;
    }
}
changed($functions, 'unused static function');

# Fixes the fail label and return_value in each wrapper function.
my $wrapper = qr/^(?:static\s+)?(?:PHP_METHOD|PHP_FUNCTION|ZEND_NAMED_FUNCTION)
    \(.*\)\s*\{\s*$/x;
my $uses = qr/\breturn_value\b|\bRETVAL_\w+|\bRETURN_\w+
    |\bINTERNAL_FUNCTION_PARAM_PASSTHRU\b/x;
my @out;
my ($labels, $voids) = (0, 0);
for (my $i = 0; $i <= $#lines; $i++) {
    my $line = $lines[$i];
    if ($line !~ $wrapper) {
        push @out, $line;
        next;
    }
    my $end = closing($i);
    my @body = @lines[$i + 1 .. $end];
    my $code = join '', grep { !/^\s*fail:/ } @body;
    if ($code !~ $jump) {
        my $before = @body;
        @body = grep { !/^\s*fail: SWIGUNUSED;\s*$/ } @body;
        $labels += $before - @body;
    }
    push @out, $line;
    unless ($code =~ $uses) {
        push @out, "  (void)return_value;\n";
        $voids++;
    }
    push @out, @body;
    $i = $end;
}
changed($labels, 'unused fail label');
changed($voids, 'wrapper function without return_value');
$text = join '', @out;

my $owners =
    $text =~ s/(\barg->newobject = zval_get_long\(&args\[1\]\));/$1 != 0;/g;
changed($owners || 0, 'thisown setter');

# Replaces one block of the runtime, which must be there exactly once.
sub swap {
    my ($old, $new, $what) = @_;
    my $count = () = $text =~ /\Q$old\E/g;
    changed($count, $what);
    die "fix-wrapper.pl: found $what $count times.\n" if $count > 1;
    $text =~ s/\Q$old\E/$new/;
}

swap(<<'END', "#include \"php.h\"\n", 'GCC pragma around php.h');
#if defined __GNUC__ && !defined __cplusplus
# if __GNUC__ >= 4
#  pragma GCC diagnostic push
#  pragma GCC diagnostic ignored "-Wdeclaration-after-statement"
# endif
#endif
#include "php.h"
#if defined __GNUC__ && !defined __cplusplus
# if __GNUC__ >= 4
#  pragma GCC diagnostic pop
# endif
#endif
END
swap(<<'END', '', 'C4505 pragma');
#ifndef SWIG_MSC_UNSUPPRESS_4505
# if defined(_MSC_VER)
#   pragma warning(disable : 4505) /* unreferenced local function has been removed */
# endif
#endif

END
swap(<<'END', '', 'CRT deprecation define');
/* Deal with Microsoft's attempt at deprecating C standard runtime functions */
#if !defined(SWIG_NO_CRT_SECURE_NO_DEPRECATE) && defined(_MSC_VER) && !defined(_CRT_SECURE_NO_DEPRECATE)
# define _CRT_SECURE_NO_DEPRECATE
#endif

END
swap(<<'END', '', 'C++ library deprecation define');
/* Deal with Microsoft's attempt at deprecating methods in the standard C++ library */
#if !defined(SWIG_NO_SCL_SECURE_NO_DEPRECATE) && defined(_MSC_VER) && !defined(_SCL_SECURE_NO_DEPRECATE)
# define _SCL_SECURE_NO_DEPRECATE
#endif

END
swap(<<'END', '', 'Intel compiler pragma');
/* Intel's compiler complains if a variable which was never initialised is
 * cast to void, which is a common idiom which we use to indicate that we
 * are aware a variable isn't used.  So we just silence that warning.
 * See: https://github.com/swig/swig/issues/192 for more discussion.
 */
#ifdef __INTEL_COMPILER
# pragma warning disable 592
#endif

END

my $set = 'REGISTER_LONG_CONSTANT(const_name, (long) pointer,';
my $at = index $text, $set;
die "fix-wrapper.pl: the module pointer constant was not found.\n" if $at < 0;
substr($text, $at, length $set) =
    'REGISTER_LONG_CONSTANT(const_name, (zend_long) (intptr_t) pointer,';

print $text;
