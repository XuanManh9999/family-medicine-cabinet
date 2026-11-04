@echo off
REM MedCabinet - Go bo tac vu Task Scheduler da dang ky
chcp 65001 >nul
schtasks /Delete /F /TN "MedCabinet Scheduler"
pause
