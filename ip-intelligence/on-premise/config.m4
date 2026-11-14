PHP_REQUIRE_CXX()
PHP_ADD_LIBRARY(stdc++, , FIFTYONEDEGREESIPINTELLIGENCEENGINE_LIBADD)

PHP_ARG_ENABLE(FiftyOneDegreesIpIntelligenceEngine, whether to enable 51Degrees IP Intelligence engine,
[ --enable-FiftyOneDegreesIpIntelligenceEngine   Enable 51Degrees on-premise IP Intelligence])

dnl IP intelligence data files are large, so 64 bit file offsets must be used
dnl when reading them (FIFTYONE_DEGREES_LARGE_DATA_FILE_SUPPORT / _FILE_OFFSET_BITS).
dnl
dnl NOTE on FIFTYONE_DEGREES_REDUCED_FILE: this define changes on-disk struct
dnl layouts. The cxx submodule builds the IP intelligence copy of common with
dnl it and with large file support (src/common/CMakeLists.txt there), which is
dnl the format the 4.5 .ipi files use, so both MUST be set here. All native
dnl objects (wrapper, C++ and C) must be compiled with the same settings, so
dnl this extension cannot share compiled common objects with device detection,
dnl which builds common without these defines.
COMMON_DEFINES="-DFIFTYONE_DEGREES_LARGE_DATA_FILE_SUPPORT=1 -DFIFTYONE_DEGREES_REDUCED_FILE=1 -D_FILE_OFFSET_BITS=64"
dnl
dnl Every symbol but get_module, which the SWIG wrapper marks as exported, is
dnl hidden. The device detection extension holds common's functions under the
dnl same names, and a PHP that loads extensions without RTLD_DEEPBIND, as
dnl PHP 8.5 on Linux does, would otherwise bind this extension's calls to the
dnl other extension's copy.
VISIBILITY="-fvisibility=hidden"
CFLAGS="$CFLAGS $COMMON_DEFINES $VISIBILITY -std=gnu11 -Wall -Wno-strict-prototypes -Wno-missing-braces -Wno-unused-variable -Wno-unused-but-set-variable -Wno-unused-function -Wno-strict-aliasing"
CXXFLAGS="${CXXFLAGS} $COMMON_DEFINES $VISIBILITY -std=gnu++17 -fpermissive -Wall -Wno-write-strings -Wno-delete-non-virtual-dtor -Wno-unused-label -Wno-unused-variable"
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

if test "$PHP_FIFTYONEDEGREESIPINTELLIGENCEENGINE" = "yes"; then
  AC_DEFINE(HAVE_FIFTYONEDEGREESIPINTELLIGENCEENGINE, 1, [Whether you have 51Degrees IP Intelligence engine Enabled])
  PHP_SUBST(FIFTYONEDEGREESIPINTELLIGENCEENGINE_LIBADD)

  AC_CONFIG_COMMANDS_PRE(mkdir -p src/php$PHP_MAJOR_VERSION)

  dnl The SWIG wrapper in src/php$PHP_MAJOR_VERSION is committed and is not
  dnl regenerated here, so every build compiles the same wrapper.
  dnl on-premise/generate-wrapper.sh regenerates it.

  AC_CONFIG_COMMANDS_PRE(cp src/php$PHP_MAJOR_VERSION/* .)

  PHP_NEW_EXTENSION(FiftyOneDegreesIpIntelligenceEngine, ipi_wrap.cxx cxx/src/ipi/*.c cxx/src/ipi/*.cpp cxx/src/common/*.c cxx/src/common/*.cpp cxx/src/ipi/graph/*.c, $ext_shared, ,,"yes")
fi
