#!/usr/bin/env bash
# LabLend setup for XAMPP on Linux (/opt/lampp)
# Run:  sudo bash setup_linux.sh
set -e
LAMPP=/opt/lampp
SRC="$(cd "$(dirname "$0")" && pwd)"
DEST="$LAMPP/htdocs/lablend"

[ -x "$LAMPP/lampp" ] || { echo "XAMPP not found in $LAMPP"; exit 1; }
[ "$(id -u)" -eq 0 ] || { echo "Please run with sudo:  sudo bash setup_linux.sh"; exit 1; }

if [ "$SRC" != "$DEST" ]; then
    echo "Copying files to $DEST ..."
    mkdir -p "$DEST"
    cp -r "$SRC"/. "$DEST"/
    rm -f "$DEST/SETUP_WINDOWS.bat" "$DEST/setup_linux.sh"
fi
chmod -R a+rX "$DEST"
chmod -R a+rwX "$DEST/assets/images/uploads"
echo "[OK] Files in $DEST"

echo "Starting Apache + MySQL ..."
"$LAMPP/lampp" start >/dev/null || true
for i in $(seq 1 30); do
    "$LAMPP/bin/mysqladmin" -u root ping >/dev/null 2>&1 && break
    sleep 2
done

echo "Creating database lablend_db (resets LabLend sample data) ..."
if ! "$LAMPP/bin/mysql" -u root < "$DEST/database/lablend.sql" 2>/dev/null; then
    read -rsp "MySQL root password: " PW; echo
    "$LAMPP/bin/mysql" -u root -p"$PW" < "$DEST/database/lablend.sql"
    echo "NOTE: put this password in $DEST/config/database.php (DB_PASS)"
fi
echo "[OK] Database lablend_db created"
echo
echo "Done!  Open http://localhost/lablend/"
echo "Admin   : admin / Lab@2026"
echo "Student : aarav@student.example / Student@123"
