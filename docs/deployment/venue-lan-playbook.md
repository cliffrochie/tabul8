# TABUL8 Venue Router & Offline LAN Deployment Playbook

This playbook covers deploying **TABUL8** on an air-gapped venue LAN for live pageants, singing competitions, and multi-round elimination events.

---

## 1. Hardware Checklist

- **Tabulation Server (Primary):** Laptop or Mini-PC (Apple Silicon / x86_64 Linux, >= 8 GB RAM, SSD) running Docker Desktop or Docker Engine + Compose v2.
- **Dedicated Event Router:** Dual-band 5 GHz Wi-Fi 6 router configured strictly for the TABUL8 subnet (e.g., `192.168.50.0/24`), with SSID hidden or WPA2/WPA3-PSK reserved exclusively for judge tablets, tabulator stations, MC cue tablet, and LED display PC.
- **Wired Ethernet Backhaul:** Cat6 cable connecting the Tabulation Server to LAN Port 1 of the router (static DHCP lease `192.168.50.10`).
- **Power Protection:** Mini UPS / battery backup on both the router and Tabulation Server so brief venue power dips do not drop WebSocket connections or SQLite state.
- **Judge Tablets (Up to 15+):** Modern Safari / Chrome browsers, auto-lock disabled, battery >= 80% + USB-C power banks.

---

## 2. Container Stack Spin-Up (`docker compose`)

All services boot offline once images are built ahead of the event:

```bash
# Pre-build while connected to internet (before arriving at venue):
docker compose build

# Start full stack on venue LAN (Nginx :80 -> App :8000, Reverb :8080, Frontend :5173):
docker compose up -d
```

### Container Architecture
- **`tabul8-app` (`backend/Dockerfile`):** Runs PHP 8.3 CLI (`php artisan serve --host=0.0.0.0 --port=8000`), automatically creates `/var/lib/tabul8/database.sqlite` in the `tabul8_db` named volume, sets SQLite `PRAGMA journal_mode=WAL; busy_timeout=5000;`, and runs `php artisan migrate --force` via `backend/docker/entrypoint.sh`.
- **`tabul8-reverb`:** Runs `php artisan reverb:start --host=0.0.0.0 --port=8080` after `tabul8-app` passes `/up` healthchecks.
- **`tabul8-frontend`:** Serves the React 19 SPA on port `5173`.
- **`tabul8-nginx` (`infra/nginx/tabul8.conf`):** Single port-80 gateway routing `/api/*`, `/sanctum/*`, `/up` to `app:8000`, `/app*` WebSocket upgrades to `reverb:8080`, and `/` to `frontend:5173`.

---

## 3. Friendly Venue Hostname (`tabulator.local` via Avahi mDNS)

On a Linux server host (Ubuntu/Debian/Raspberry Pi OS), install the provided Avahi HTTP service definition so iPad/Android tablets and the Arena LED PC connect via `http://tabulator.local` without memorizing IP addresses:

```bash
sudo apt-get install -y avahi-daemon
sudo hostnamectl set-hostname tabulator
sudo cp infra/avahi/tabulator.service /etc/avahi/services/tabulator.service
sudo systemctl restart avahi-daemon
```

> **macOS Hosts:** macOS Bonjour advertises `<ComputerName>.local` natively. Set Local Hostname to `tabulator` in **System Settings -> General -> Sharing -> Local Hostname** (`tabulator.local`).

---

## 4. Pre-Show Quorum & Setup Dry-Run Checklist

1. Seed or verify event setup from `/console/setup` (or load rehearsal fixtures via `docker compose exec app php artisan db:seed`).
2. Confirm the **Pre-Flight Readiness Banner** at the top of `/console/setup` shows **0 blockers**:
   - Event exists and has >= 1 round.
   - Each round has leaf criteria summing to `100.00%`.
   - At least 3 active judges configured (with one Chairman designated).
   - Multi-round advancement funnel counts strictly descend across rounds.
3. Click **[Download Tent Cards PDF]** under `WF-13 · Judges & PINs` and distribute 6-digit PIN cards to each judge tablet.
4. Open `/display` on the Arena LED projection computer (`F11` fullscreen) and confirm live WebSocket status badge (`Live Connected`).
5. Open `/mc` on the Stage Manager / MC tablet and confirm stage cue switching updates both Judge tablets (`/judge`) and the LED screen.
6. Run a quick lock/reopen/PDF smoke check and confirm `[Export Official Tally PDF]` returns a valid SHA-256 stamped A4 landscape PDF (`FINAL & OFFICIAL` after round finalization).

---

## 5. Live Emergency Contingencies

| Incident | Immediate Operator Action |
| :--- | :--- |
| **Judge tablet drops Wi-Fi temporarily** | Judge keeps scoring offline; `tabul8` IndexedDB outbox queues `client_mutation_id` packets and auto-flushes with idempotent deduplication upon reconnect. |
| **Judge tablet battery dies / hardware swap needed** | On `/console/setup -> WF-13`, click **[Regenerate PIN]** for that judge. Their old token is immediately revoked and the judge signs into the spare tablet with the new 6-digit PIN. |
| **Judge unable to complete round (illness/absence)** | On `/console`, exclude the inactive judge from the active round with a mandatory audit reason, restoring quorum so round finalization and Official Tally PDF export can proceed. |
| **Cutoff tie at elimination boundary (`409 errors.cutoff_tie`)** | Review the tied contestants inside `FinalizeRoundModal`, select explicit qualifiers or wildcard overrides with a written Chairman override reason, and submit. |
| **Post-finalization scoring correction required** | Use **[Reopen Round]** on `/console` (`tabulator` / `super_admin`) with a mandatory audit reason to unlock the round, make corrections, and re-finalize. |
