# Troubleshooting

Various failures may be encountered in the process of using static-php-cli, 
here will describe how to check the errors by yourself and report Issue.

## Download Failure

Problems with downloading resources are one of the most common problems with spc. 
The main reason is that the addresses used for SPC download resources are generally the official website of the corresponding project or GitHub, etc.,
and these websites may occasionally go down and block IP addresses.
Currently, version 2.0.0 has not added an automatic retry mechanism, so after encountering a download failure, 
you can try to call the download command multiple times. If you confirm that the address is indeed inaccessible, 
you can submit an Issue or PR to update the url or download type.

## Doctor Can't Fix Something

In most cases, the doctor module can automatically repair and install missing system environments, 
but there are also special circumstances where the automatic repair function cannot be used normally.

Due to system limitations (for example, software such as Visual Studio cannot be automatically installed under Windows),
the automatic repair function cannot be used for some projects.
When encountering a function that cannot be automatically repaired, 
if you encounter the words `Some check items can not be fixed`,
it means that it cannot be automatically repaired.
Please submit an issue according to the method displayed on the terminal or repair the environment yourself.

## Compile Error

When you encounter a compilation error, if the `--debug` log is not enabled, please enable the debug log first,
and then determine the command that reported the error.
The error terminal output is very important for fixing compilation errors.
When submitting an issue, please upload the last error fragment of the terminal log (or the entire terminal log output),
and include the `spc` command and parameters used.
