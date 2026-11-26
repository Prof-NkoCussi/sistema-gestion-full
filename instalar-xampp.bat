@echo off
REM instalar-xampp.bat: deja el sistema listo para probar en XAMPP (Windows)
REM Crea la base escuela con los datos de prueba, el usuario de la base y los usuarios de demo.
REM Solo para practicar en la propia PC: las claves de demo son publicas (estan en el README).
REM Uso: arrancar MySQL desde el panel de XAMPP y hacer doble clic en este archivo.

REM setlocal: las variables de este archivo no quedan en la consola
setlocal
REM %~dp0 es la carpeta de este archivo; /d cambia tambien de unidad
cd /d "%~dp0"

REM mysql.exe de XAMPP: si XAMPP esta en otra carpeta, cambiar esta linea
set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
if not exist "%MYSQL%" (
    echo No se encontro %MYSQL%
    echo Si XAMPP no esta en C:\xampp, editar la linea "set MYSQL=" de este archivo.
    goto fin
)

echo OJO: si la base escuela ya existe, se borra y se vuelve a crear.
echo Para cancelar, cerrar esta ventana. Para seguir, tocar una tecla.
pause >nul

echo Creando la base escuela con los datos de prueba...
REM root sin clave, como viene XAMPP. Si root tiene clave, agregar -p despues de -u root
"%MYSQL%" -u root --default-character-set=utf8mb4 < database\escuela.sql
REM errorlevel 1 o mas: mysql fallo
if errorlevel 1 goto error_mysql

echo Creando el usuario de la base y los usuarios de demo...
"%MYSQL%" -u root --default-character-set=utf8mb4 < database\demo_xampp.sql
if errorlevel 1 goto error_mysql

REM config.php: si no existe, se copia el de XAMPP; si ya existe, no se toca
if exist config\config.php (
    echo config\config.php ya existe y no se modifico.
    echo Si no se puede entrar al sistema, borrarlo y volver a correr este archivo.
) else (
    copy config\config.xampp.php config\config.php >nul
    echo Se creo config\config.php con la configuracion de XAMPP.
)

REM %%~nxI: el nombre de la carpeta del proyecto, para armar la direccion
for %%I in ("%~dp0.") do set "CARPETA=%%~nxI"
echo.
echo Listo. Abrir http://localhost/%CARPETA%/
echo Usuarios de demo: admin / Admin-Demo-2026, preceptor1 / Preceptor-Demo-2026, docente1 / Docente-Demo-2026
goto fin

:error_mysql
echo.
echo Fallo mysql. Revisar que MySQL este arrancado en el panel de XAMPP.
echo Si root tiene clave, agregar -p despues de -u root en este archivo.

:fin
echo.
REM pause: la ventana queda abierta hasta tocar una tecla
pause
