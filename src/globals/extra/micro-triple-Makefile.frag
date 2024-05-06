
micro: $(SAPI_MICRO_PATH)

micro_2s_objs:
	rm $(MICRO_2STAGE_OBJS) || :
	$(MAKE) $(MICRO_2STAGE_OBJS)

EXTRA_MICRO_BUILD_COMMANDS=
POST_MICRO_BUILD_COMMANDS=$(STRIP) $(MICRO_STRIP_FLAGS) $(SAPI_MICRO_PATH)

$(SAPI_MICRO_PATH): $(PHP_GLOBAL_OBJS) $(PHP_BINARY_OBJS) $(PHP_MICRO_OBJS)
	$(MAKE) micro_2s_objs SFX_FILESIZE=0xcafebabe
	$(BUILD_MICRO)
	$(EXTRA_MICRO_BUILD_COMMANDS)
	$(POST_MICRO_BUILD_COMMANDS)
	$(MAKE) micro_2s_objs SFX_FILESIZE=`$(STAT_SIZE) $(SAPI_MICRO_PATH)`
	$(BUILD_MICRO)
	$(EXTRA_MICRO_BUILD_COMMANDS)
	$(POST_MICRO_BUILD_COMMANDS)
	$(MAKE) micro_2s_objs SFX_FILESIZE=`$(STAT_SIZE) $(SAPI_MICRO_PATH)`
	$(BUILD_MICRO)
	$(EXTRA_MICRO_BUILD_COMMANDS)
	$(POST_MICRO_BUILD_COMMANDS)
	$(MAKE) micro_2s_objs SFX_FILESIZE=`$(STAT_SIZE) $(SAPI_MICRO_PATH)`
	$(BUILD_MICRO)
	$(EXTRA_MICRO_BUILD_COMMANDS)
	$(POST_MICRO_BUILD_COMMANDS)

MICRO_EXES = sapi/micro/tests/simpleecho.exe sapi/micro/tests/fakecmd.exe

$(MICRO_EXES): $(SAPI_MICRO_PATH)
	@binname=$@;\
	cat $(SAPI_MICRO_PATH) $${binname%.exe}.php > $@ || {\
		rm $@; \
		exit 1; \
	}
	@chmod 0755 $@

MICRO_FAKECMD=sapi/micro/tests/fakecmd.exe

micro_test: $(SAPI_MICRO_PATH) $(MICRO_EXES)
	@[ x"hello world" = "x`sapi/micro/tests/simpleecho.exe nonce world`" ] || {\
		echo sanity check for micro.sfx failed, the sfx generated may be corrupt. >&2 ;\
		exit 1;\
	}
	@SKIP_IO_CAPTURE_TESTS=yes \
	TEST_PHP_EXECUTABLE=$(MICRO_FAKECMD) \
	TEST_PHP_SRCDIR=$(top_srcdir) \
	CC="$(CC)" \
		$(MICRO_FAKECMD) -n $(PHP_TEST_SETTINGS) $(top_srcdir)/run-tests.php -n $(TESTS); \
	exit $$?;
