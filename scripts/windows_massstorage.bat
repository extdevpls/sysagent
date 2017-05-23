@echo off
reg query "HKLM\SYSTEM\CurrentControlSet\Services\UsbStor" /v Start
