@echo Off
REM 32bit
wmic datafile where name="C:\\Program Files (x86)\\Mozilla Firefox\\firefox.exe" get Version /value
REM 64bit
wmic datafile where name="C:\\Program Files\\Mozilla Firefox\\firefox.exe" get Version /value