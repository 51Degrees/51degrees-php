PHP_REQUIRE_CXX()
PHP_ADD_LIBRARY(stdc++, , FIFTYONEDEGREESHASHENGINE_LIBADD)

PHP_ARG_ENABLE(FiftyOneDegreesHashEngine, whether to enable 51Degrees Hash engine,
[ --enable-FiftyOneDegreesHashEngine   Enable 51Degrees Hash Device Detection])

dnl Every symbol but get_module, which the SWIG wrapper marks as exported, is
dnl hidden. The IP intelligence extension holds common's functions under the
dnl same names, built with different settings, and a PHP that loads
dnl extensions without RTLD_DEEPBIND, as PHP 8.5 on Linux does, would
dnl otherwise bind this extension's calls to the other's copy.
VISIBILITY="-fvisibility=hidden"
CFLAGS="$CFLAGS $VISIBILITY -std=gnu11 -Wall -Werror -Wno-missing-braces -Wno-unused-variable -Wno-unused-but-set-variable -Wno-unused-function -Wno-strict-aliasing"
CXXFLAGS="${CXXFLAGS} $VISIBILITY -std=gnu++17 -fpermissive -Wall -Werror -Wno-write-strings -Wno-delete-non-virtual-dtor -Wno-unused-label -Wno-unused-variable"
case "$host_os" in
linux*) LDFLAGS="$LDFLAGS -latomic" ;;
esac
PHP_SUBST([CFLAGS])
PHP_SUBST([CXXFLAGS])
PHP_SUBST([LDFLAGS])

dnl Check PHP version:
AC_MSG_CHECKING(PHP version)
if test ! -z "$PHP_CONFIG"; then
  PHP_VERSION=`$PHP_CONFIG --version 2>/dev/null`
fi
if test x"$PHP_VERSION" = "x"; then
    AC_MSG_WARN([none])
else
    PHP_MAJOR_VERSION=`echo $PHP_VERSION | sed -e 's/\([[0-9]]*\)\.\([[0-9]]*\)\.\([[0-9]]*\).*/\1/g' 2>/dev/null`
    PHP_MINOR_VERSION=`echo $PHP_VERSION | sed -e 's/\([[0-9]]*\)\.\([[0-9]]*\)\.\([[0-9]]*\).*/\2/g' 2>/dev/null`
    PHP_RELEASE_VERSION=`echo $PHP_VERSION | sed -e 's/\([[0-9]]*\)\.\([[0-9]]*\)\.\([[0-9]]*\).*/\3/g' 2>/dev/null`
    AC_MSG_RESULT([$PHP_MAJOR_VERSION].[$PHP_MINOR_VERSION])
fi

if test "$PHP_FIFTYONEDEGREESHASHENGINE" = "yes"; then
  AC_DEFINE(HAVE_FIFTYONEDEGREESHASHENGINE, 1, [Whether you have 51Degrees Hash engine Enabled])
  PHP_SUBST(FIFTYONEDEGREESHASHENGINE_LIBADD)

  AC_CONFIG_COMMANDS_PRE(mkdir -p src/php$PHP_MAJOR_VERSION)

  dnl The SWIG wrapper in src/php$PHP_MAJOR_VERSION is committed and is not
  dnl regenerated here, so every build compiles the same wrapper.
  dnl on-premise/generate-wrapper.sh regenerates it.

  AC_CONFIG_COMMANDS_PRE(cp src/php$PHP_MAJOR_VERSION/* .)

  PHP_NEW_EXTENSION(FiftyOneDegreesHashEngine, hash_wrap.cxx cxx/src/dd/*.c cxx/src/dd/*.cpp cxx/src/common/*.c cxx/src/common/*.cpp cxx/src/dd/hash/*.c cxx/src/dd/hash/*.cpp, $ext_shared, ,,"yes")
fi
