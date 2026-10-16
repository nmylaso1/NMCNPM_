@echo off
TITLE PHP Server Starter

echo Đang kiểm tra cấu hình...
SET PHP_PATH=php-8.5.1-nts-Win32-vs17-x64
SET PHP_EXE=%PHP_PATH%\php.exe
SET PHP_INI=%PHP_PATH%\php.ini

if not exist "%PHP_EXE%" (
    where php >nul 2>&1
    if errorlevel 1 (
        echo [LOI] Khong tim thay PHP runtime trong repo hoac trong PATH.
        pause
        exit
    )

    echo Đang khởi động PHP Server tại http://localhost:8000
    echo Su dung PHP tu PATH
    echo.
    echo Nhan Ctrl+C de dung server.
    echo.
    php -S localhost:8000
    pause
    exit
)

echo Đang khởi động PHP Server tại http://localhost:8000
echo Su dung runtime bundled: %PHP_EXE%
echo.
echo Nhan Ctrl+C de dung server.
echo.
"%PHP_EXE%" -c "%PHP_INI%" -S localhost:8000
pause
