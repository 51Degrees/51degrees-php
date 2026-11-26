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

# Removes static functions that nothing calls, until none is left, because
# removing one can leave another without a caller.
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
        $removed = 1;
        last;
    }
}

# Fixes the fail label and return_value in each wrapper function.
my $wrapper = qr/^(?:static\s+)?(?:PHP_METHOD|PHP_FUNCTION|ZEND_NAMED_FUNCTION)
    \(.*\)\s*\{\s*$/x;
my $uses = qr/\breturn_value\b|\bRETVAL_\w+|\bRETURN_\w+
    |\bINTERNAL_FUNCTION_PARAM_PASSTHRU\b/x;
my @out;
for (my $i = 0; $i <= $#lines; $i++) {
    my $line = $lines[$i];
    if ($line !~ $wrapper) {
        push @out, $line;
        next;
    }
    my $end = closing($i);
    my @body = @lines[$i + 1 .. $end];
    my $code = join '', grep { !/^\s*fail:/ } @body;
    @body = grep { !/^\s*fail: SWIGUNUSED;\s*$/ } @body if $code !~ $jump;
    push @out, $line;
    push @out, "  (void)return_value;\n" unless $code =~ $uses;
    push @out, @body;
    $i = $end;
}
$text = join '', @out;

$text =~ s/(\barg->newobject = zval_get_long\(&args\[1\]\));/$1 != 0;/g;

my $set = 'REGISTER_LONG_CONSTANT(const_name, (long) pointer,';
my $at = index $text, $set;
die "fix-wrapper.pl: the module pointer constant was not found.\n" if $at < 0;
substr($text, $at, length $set) =
    'REGISTER_LONG_CONSTANT(const_name, (zend_long) (intptr_t) pointer,';

print $text;
