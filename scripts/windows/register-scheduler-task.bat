@echo off
REM ==================================================================
REM  MedCabinet - Dang ky Windows Task Scheduler chay "php artisan schedule:run" moi phut
REM  (chay ngam, khong hien cua so). Chi can chay file nay MOT LAN.
REM  Go bo: chay unregister-scheduler-task.bat
REM ==================================================================
chcp 65001 >nul
setlocal

set "PROJECT_DIR=%~dp0..\.."
for %%I in ("%PROJECT_DIR%") do set "PROJECT_DIR=%%~fI"

set "PHP_BIN="
for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP_BIN set "PHP_BIN=%%P"
if not defined PHP_BIN set "PHP_BIN=C:\xampp\php\php.exe"

if not exist "%PHP_BIN%" (
    echo Khong tim thay php.exe. Hay sua bien PHP_BIN trong file nay cho dung duong dan XAMPP.
    pause
    exit /b 1
)

set "TASK_NAME=MedCabinet Scheduler"
set "RUNNER=%~dp0run-schedule-hidden.vbs"

schtasks /Create /F /SC MINUTE /MO 1 /TN "%TASK_NAME%" ^
  /TR "wscript.exe \"%RUNNER%\" \"%PHP_BIN%\" \"%PROJECT_DIR%\artisan\""

if errorlevel 1 (
    echo Dang ky that bai. Thu chay lai file nay bang quyen cua tai khoan dang dang nhap.
) else (
    echo Da dang ky tac vu "%TASK_NAME%" chay moi phut.
    echo Kiem tra: mo Task Scheduler ^> Task Scheduler Library ^> %TASK_NAME%
)
pause
