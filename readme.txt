Scripts and utils to use with PDP-11 related projects:

preprocess.php
preprocess .mac files, include binaries, convert hex to octals, etc.

lst2bin.php
make binaries (.sav or .bin) from macro-11 .lst files 

macro11.exe, sysmac.sml
version of macro-11 compiler for PDP-11
https://github.com/j-hoppe/MACRO11
(changed a bit by me)

zx0.exe
ZX0 compression utility
https://github.com/einar-saukas/ZX0/

rt11dsk.exe
utility for working with RT-11 .dsk disk images by N.Zeemin
https://github.com/nzeemin/ukncbtl-utils/

bkdecmd.exe
utility for working with BK-001x disk images by N.Zeemin
https://github.com/nzeemin/bkdecmd

uknccomsender.exe
sending files over RS-232 to MS-0511
modified version from N.Zeemin, original https://github.com/nzeemin/ukncbtl-utils/tree/master/UkncComSender

rt11.exe, system.dsk
PDP-11 emulator for windows console to compile macro-11 file more native way like:
rt11.exe macro hello
rt11.exe link hello
