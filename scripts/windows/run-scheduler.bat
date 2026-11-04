@echo off
REM ==================================================================
REM  MedCabinet - Chay bo lap lich khi demo / phat trien (giu cua so mo)
REM  Moi phut kiem tra va chay: nhac uong thuoc (5 phut/lan),
REM  quet han dung & ton kho tu thuoc (06:00 hang ngay)
REM  Dong cua so nay = dung bo lap lich.
REM ==================================================================
chcp 65001 >nul
cd /d "%~dp0..\.."

set "PHP_BIN=php"
where php >nul 2>nul || set "PHP_BIN=C:\xampp\php\php.exe"

echo Dang chay bo lap lich MedCabinet (Ctrl+C de dung)...
"%PHP_BIN%" artisan schedule:work
