@echo off
TITLE PHP Server Starter
SET PHP_PATH=php-8.5.1-nts-Win32-vs17-x64
SET PHP_EXE=%PHP_PATH%\php.exe
SET PHP_INI=%PHP_PATH%\php.ini

echo Đang kiểm tra cấu hình...
if not exist "%PHP_EXE%" (
    echo [LOI] Khong tim thay file php.exe tai %PHP_EXE%
    pause
    exit
)

echo Đang khởi động PHP Server tại http://localhost:8000
echo Su dung file cau hinh: %PHP_INI%
echo.
echo Nhan Ctrl+C de dung server.
echo.
"%PHP_EXE%" -c "%PHP_INI%" -S localhost:8000
pause
