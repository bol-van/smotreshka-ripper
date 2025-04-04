@echo off
set PHP=C:\Program Files\php\php.exe
set VLC=C:\Program Files\VideoLAN\VLC\vlc.exe
set EMAIL="<email>";
set PASS="<password>";
set PLAYLIST=%TEMP%\smotreshka.m3u
"%PHP%" %~dp0smotreshka.php %EMAIL% %PASS% "%PLAYLIST%"
if ERRORLEVEL 1 (
 echo PHP Error
 pause
 goto :eof
)
start "" "%VLC%" --no-playlist-autostart "%PLAYLIST%"
