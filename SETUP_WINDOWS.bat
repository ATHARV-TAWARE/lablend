@echo off
setlocal EnableExtensions
title LabLend setup
echo ==================================================
echo   LabLend setup for XAMPP on Windows
echo   (copies files, starts Apache + MySQL, creates lablend_db)
echo ==================================================
echo.

rem ---- 1. Find XAMPP ----
set "XAMPP=C:\xampp"
if not exist "%XAMPP%\mysql\bin\mysql.exe" (
    echo XAMPP was not found in C:\xampp
    set /p "XAMPP=Type your XAMPP folder (for example D:\xampp) and press Enter: "
)
if not exist "%XAMPP%\mysql\bin\mysql.exe" (
    echo.
    echo ERROR: could not find "%XAMPP%\mysql\bin\mysql.exe". Is XAMPP installed?
    pause
    exit /b 1
)
echo [OK] XAMPP found in %XAMPP%

rem ---- 2. Copy the project into htdocs\lablend ----
set "SRC=%~dp0"
if "%SRC:~-1%"=="\" set "SRC=%SRC:~0,-1%"
set "DEST=%XAMPP%\htdocs\lablend"
if /I "%SRC%"=="%DEST%" (
    echo [OK] Already inside %DEST%
) else (
    echo Copying files to %DEST% ...
    robocopy "%SRC%" "%DEST%" /E /NFL /NDL /NJH /NJS /NP /XF SETUP_WINDOWS.bat setup_linux.sh >nul
    if errorlevel 8 (
        echo ERROR: copying failed. Try running this file as Administrator.
        pause
        exit /b 1
    )
    echo [OK] Files copied
)

rem ---- 3. Start MySQL and Apache if they are not running ----
tasklist /FI "IMAGENAME eq mysqld.exe" | find /I "mysqld.exe" >nul || (
    echo Starting MySQL ...
    start "XAMPP MySQL" /MIN "%XAMPP%\mysql_start.bat"
)
tasklist /FI "IMAGENAME eq httpd.exe" | find /I "httpd.exe" >nul || (
    echo Starting Apache ...
    start "XAMPP Apache" /MIN "%XAMPP%\apache_start.bat"
)

echo Waiting for MySQL to be ready ...
set /a TRIES=0
:waitmysql
"%XAMPP%\mysql\bin\mysqladmin.exe" -u root ping >nul 2>&1 && goto mysqlready
"%XAMPP%\mysql\bin\mysqladmin.exe" -u root ping 2>&1 | find /I "denied" >nul && goto mysqlready
set /a TRIES+=1
if %TRIES% GEQ 30 (
    echo ERROR: MySQL did not start. Open the XAMPP Control Panel, click Start next to MySQL, then run this file again.
    pause
    exit /b 1
)
timeout /t 2 /nobreak >nul
goto waitmysql
:mysqlready
echo [OK] MySQL is running

rem ---- 4. Create the database (lablend_db) with sample data ----
echo Creating database lablend_db (this resets LabLend's sample data) ...
"%XAMPP%\mysql\bin\mysql.exe" -u root < "%DEST%\database\lablend.sql" 2>nul
if errorlevel 1 (
    echo The MySQL user root seems to have a password.
    set /p "PW=Type the MySQL root password and press Enter: "
    call "%XAMPP%\mysql\bin\mysql.exe" -u root -p%%PW%% < "%DEST%\database\lablend.sql"
    if errorlevel 1 (
        echo ERROR: could not import the database. Import database\lablend.sql in phpMyAdmin instead.
        pause
        exit /b 1
    )
    echo NOTE: put this password in %DEST%\config\database.php  ^(DB_PASS^)
)
echo [OK] Database lablend_db created

rem ---- 5. Open the site ----
echo.
echo ==================================================
echo   Done!  Opening http://localhost/lablend/
echo   Admin   : admin / Lab@2026        (http://localhost/lablend/admin/)
echo   Student : aarav@student.example / Student@123
echo ==================================================
start "" "http://localhost/lablend/"
pause
