/* *********************************************************************
 * This Original Work is copyright of 51 Degrees Mobile Experts Limited.
 * Copyright 2026 51 Degrees Mobile Experts Limited, Davidson House,
 * Forbury Square, Reading, Berkshire, United Kingdom RG1 3EU.
 *
 * This Original Work is licensed under the European Union Public Licence
 * (EUPL) v.1.2 and is subject to its terms as set out below.
 *
 * If a copy of the EUPL was not distributed with this file, You can obtain
 * one at https://opensource.org/licenses/EUPL-1.2.
 *
 * The 'Compatible Licences' set out in the Appendix to the EUPL (as may be
 * amended by the European Commission) shall be deemed incompatible for
 * the purposes of the Work and the provisions of the compatibility
 * clause in Article 5 of the EUPL shall not apply.
 *
 * If using the Work as, or as part of, a network application, by
 * including the attribution notice(s) required under Article 5 of the EUPL
 * in the end user terms of the application under an appropriate heading,
 * such notice(s) shall fulfill the requirements of that article.
 * ********************************************************************* */

/*
 * PHP entry point for the SWIG interface to the IP intelligence on-premise
 * engine. It includes the interface in the cxx submodule (src/ipi/ipi.i).
 * generate-wrapper.sh runs SWIG on this file, naming the module
 * FiftyOneDegreesIpIntelligenceEngine.
 */

/*
 * A weighted list's get returns a copy of the weighted value, which the PHP
 * object owns. SWIG would return a reference into the list, and a list that
 * is itself a returned value, as in $w->getValue()->get(0)->getValue(), is
 * freed by PHP as soon as get returns, leaving the reference pointing at
 * freed memory.
 */
%define IPI_RETURN_COPY(TYPE)
%typemap(out, phptype="SWIGTYPE") const TYPE & {
  SWIG_SetPointerZval($result, (void *)new TYPE(*$1), $1_descriptor, 1);
}
%enddef
IPI_RETURN_COPY(WeightedValue< std::string >)
IPI_RETURN_COPY(WeightedValue< std::vector< uint8_t > >)
IPI_RETURN_COPY(WeightedValue< bool >)
IPI_RETURN_COPY(WeightedValue< int >)
IPI_RETURN_COPY(WeightedValue< double >)

%include "cxx/src/ipi/ipi.i"

/*
 * Class names.
 *
 * The Device Detection extension has classes of the same names as many of
 * this extension's, such as VectorStringSwig and ConfigBaseSwig, because both
 * wrap the same common C++ classes. PHP keeps one table of class names, and an
 * extension that registers a name already there frees the class it replaces,
 * which the other extension still uses. So generate-wrapper.sh passes SWIG
 * -prefix with IPI_CLASS_PREFIX, and every class of this extension starts with
 * it, for example FiftyOneDegreesIpiEngineIpiSwig. The class of the module's
 * own functions and constants, named after the module before, is named
 * IPI_CLASS_PREFIX alone.
 *
 * The earlier names are deprecated. Once every extension has started, each
 * earlier name that no other class has is registered as another name for this
 * extension's class, so code written for the earlier names keeps working
 * whenever the Device Detection extension is not loaded.
 */

%{
    #define IPI_CLASS_PREFIX "FiftyOneDegreesIpi"
    #define IPI_MODULE_NAME "FiftyOneDegreesIpIntelligenceEngine"

    static zend_result (*ipi_next_post_startup)(void) = NULL;
    static bool ipi_earlier_names_pending = false;

    /*
     * Registers the earlier name of each of this extension's classes, where
     * no class has that name. The classes are listed first, because adding a
     * name changes the table being read.
     */
    static void ipi_register_earlier_names(bool persistent) {
        const size_t prefixLength = strlen(IPI_CLASS_PREFIX);
        uint32_t count = 0;
        zend_class_entry **classes = (zend_class_entry **)malloc(
            sizeof(zend_class_entry *) *
            (zend_hash_num_elements(CG(class_table)) + 1));
        zend_string *key;
        zend_class_entry *ce;
        if (classes == NULL) {
            return;
        }
        ZEND_HASH_FOREACH_STR_KEY_PTR(CG(class_table), key, ce) {
            if (key != NULL &&
                ce->type == ZEND_INTERNAL_CLASS &&
                ce->info.internal.module != NULL &&
                strcmp(ce->info.internal.module->name, IPI_MODULE_NAME) == 0 &&
                zend_string_equals_ci(key, ce->name) &&
                strncmp(ZSTR_VAL(ce->name), IPI_CLASS_PREFIX,
                    prefixLength) == 0) {
                classes[count++] = ce;
            }
        } ZEND_HASH_FOREACH_END();
        for (uint32_t i = 0; i < count; i++) {
            const char *earlier = ZSTR_VAL(classes[i]->name) + prefixLength;
            if (*earlier == '\0') {
                earlier = IPI_MODULE_NAME;
            }
            /* The table is keyed by the lower case name. */
            char lower[256];
            const size_t length = strlen(earlier);
            if (length >= sizeof(lower)) {
                continue;
            }
            zend_str_tolower_copy(lower, earlier, length);
            if (!zend_hash_str_exists(CG(class_table), lower, length)) {
                zend_register_class_alias_ex(
                    earlier, length, classes[i], persistent);
            }
        }
        free(classes);
    }

    /* Runs once every extension has registered its classes. */
    static zend_result ipi_post_startup(void) {
        ipi_register_earlier_names(true);
        return ipi_next_post_startup != NULL ? ipi_next_post_startup() : SUCCESS;
    }
%}

/*
 * Optional global engine.
 *
 * Constructing the engine on every request re-opens the (potentially multi
 * gigabyte) data file each time. To allow a long-running SAPI (for example the
 * PHP built-in server or php-fpm) to load the data file once and reuse it, the
 * module can build a single global engine at startup from php.ini settings and
 * expose it via ipi_engine_get().
 *
 * This is entirely OPTIONAL and OFF by default: the global engine is only built
 * when FiftyOneDegreesIpIntelligenceEngine.data_file is set to an existing file.
 * When it is not set the module loads normally and callers construct
 * FiftyOneDegreesIpiEngineIpiSwig directly (as IpIntelligenceOnPremise.php and
 * the tests do), so existing behaviour is unchanged. The accessor is named
 * ipi_engine_get (not
 * engine_get) so it does not clash with the device detection extension's global
 * engine when both extensions are loaded in the same process (mixed examples).
 */

%{
    #include <string.h>
    #include <sys/stat.h>

    EngineIpi *ipi_engine = NULL;
    ConfigIpi *ipi_engine_config = NULL;
    RequiredPropertiesConfig *ipi_engine_properties = NULL;

    PHP_INI_BEGIN()
    PHP_INI_ENTRY("FiftyOneDegreesIpIntelligenceEngine.data_file", "", PHP_INI_ALL, NULL)
    PHP_INI_ENTRY("FiftyOneDegreesIpIntelligenceEngine.performance_profile", NULL, PHP_INI_ALL, NULL)
    PHP_INI_ENTRY("FiftyOneDegreesIpIntelligenceEngine.required_properties", "", PHP_INI_ALL, NULL)
    PHP_INI_END()
%}

%immutable ipi_engine;
EngineIpi *ipi_engine;

%minit {
    /*
     * An extension loaded when PHP starts registers the earlier class names
     * after every extension has started. One loaded later with dl() starts
     * alone, and registers them when its first request starts.
     */
    if (type == MODULE_PERSISTENT) {
        ipi_next_post_startup = zend_post_startup_cb;
        zend_post_startup_cb = ipi_post_startup;
    } else {
        ipi_earlier_names_pending = true;
    }

    REGISTER_INI_ENTRIES();
    char *filePath = INI_STR("FiftyOneDegreesIpIntelligenceEngine.data_file");
    struct stat sb;
    if (filePath != NULL && strlen(filePath) > 0 && stat(filePath, &sb) == 0) {
        char *propertyList = INI_STR("FiftyOneDegreesIpIntelligenceEngine.required_properties");
        ipi_engine_config = new ConfigIpi();
        /*
         * The engine built when PHP starts is shared by the workers a process
         * manager forks from this process. Every profile but MaxPerformance
         * keeps the data file open, and forked workers sharing those handles
         * corrupt each other's reads, so this engine always loads the whole
         * file. IpIntelligenceOnPremise refuses any other php.ini profile.
         */
        ipi_engine_config->setMaxPerformance();
        ipi_engine_config->setUseUpperPrefixHeaders(false);
        if (propertyList != NULL && strlen(propertyList) > 0) {
            ipi_engine_properties = new RequiredPropertiesConfig(propertyList);
        } else {
            ipi_engine_properties = new RequiredPropertiesConfig();
        }
        ipi_engine = new EngineIpi(filePath, ipi_engine_config, ipi_engine_properties);
    }
}

%rinit {
    if (ipi_earlier_names_pending) {
        ipi_earlier_names_pending = false;
        ipi_register_earlier_names(false);
    }
}

%mshutdown {
    if (ipi_engine != NULL) { delete ipi_engine; ipi_engine = NULL; }
    if (ipi_engine_properties != NULL) { delete ipi_engine_properties; ipi_engine_properties = NULL; }
    if (ipi_engine_config != NULL) { delete ipi_engine_config; ipi_engine_config = NULL; }
}
