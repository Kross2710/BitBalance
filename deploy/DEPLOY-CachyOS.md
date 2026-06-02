# Deploy BitBalance trên CachyOS + host bằng ngrok + SSH từ xa

Hướng dẫn deploy bản **Express + Vue** (không phải PHP/RMIT) lên một máy CachyOS,
host ra ngoài bằng **ngrok** (1 tunnel duy nhất), deploy lại bằng **script**, và
SSH vào máy từ xa qua **Tailscale** (mạng closed-port/CGNAT không mở được port).

Kiến trúc đã chốt: `npm run build` ra `client/dist`, Express serve cả `/api` lẫn
SPA trên **một origin `:3000`** → chỉ cần **1 ngrok tunnel**, cookie same-origin.

---

## 0. Tổng quan luồng

```
[ Trình duyệt ]  --HTTPS-->  [ ngrok edge ]  --tunnel-->  [ CachyOS :3000 Express ]
                                                                |
                                                          [ MariaDB :3306 ]
[ Máy bạn ở xa ] --Tailscale (100.x)--> SSH vào CachyOS để chạy deploy.sh
```

---

## 1. Cài đặt phụ thuộc (chạy 1 lần trên CachyOS)

CachyOS dùng `pacman` (nền Arch).

```bash
sudo pacman -Syu --needed git nodejs npm mariadb php
```

> `php` chỉ cần nếu muốn chạy `include/migrations/migrate.php`. Bỏ ra nếu bạn
> apply file `.sql` thủ công.

Kiểm tra Node (cần >= 20, repo test trên 24):

```bash
node -v && npm -v
```

### MariaDB (thay cho MySQL — tương thích hoàn toàn)

```bash
# Khởi tạo data dir lần đầu:
sudo mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql
sudo systemctl enable --now mariadb
sudo mariadb-secure-installation        # đặt root password, bỏ test db
```

Tạo database + user cho app:

```bash
sudo mariadb -u root -p <<'SQL'
CREATE DATABASE bitbalance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bituser'@'localhost' IDENTIFIED BY 'doi-mat-khau-nay';
GRANT ALL PRIVILEGES ON bitbalance.* TO 'bituser'@'localhost';
FLUSH PRIVILEGES;
SQL
```

Nạp schema + dữ liệu: import dump bạn đang dùng (XAMPP `test`) vào `bitbalance`:

```bash
mariadb -u bituser -p bitbalance < duong-dan-dump.sql
```

---

## 2. Clone repo + cấu hình env

```bash
cd ~
git clone https://github.com/Kross2710/BitBalance.git BitBalance-2.0---Calorie-Tracker
cd BitBalance-2.0---Calorie-Tracker
git checkout claude/express-vue-migration-XoMbE   # hoặc main sau khi PR merge
```

Tạo `server/.env` (KHÔNG commit — đã nằm trong .gitignore):

```bash
cp server/.env.example server/.env
```

Sửa `server/.env` cho production:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=bitbalance
DB_USER=bituser
DB_PASSWORD=doi-mat-khau-nay

PORT=3000
SESSION_SECRET=<chuoi-ngau-nhien-dai>          # tạo: openssl rand -hex 32
# QUAN TRỌNG: ngrok là HTTPS -> cookie phải Secure, nếu không session rớt:
COOKIE_SECURE=true
# Same-origin rồi (Express serve SPA) nên CORS không cần, để mặc định cũng được.
CLIENT_ORIGIN=https://<ten-cua-ban>.ngrok-free.app

# Google OAuth (nếu bật): redirect URI phải trỏ đúng domain ngrok public:
# GOOGLE_CLIENT_ID=...
# GOOGLE_CLIENT_SECRET=...
# GOOGLE_REDIRECT_URI=https://<ten-cua-ban>.ngrok-free.app/api/auth/google/callback

# AI Coach (nếu bật): điền GEMINI_API_KEY hoặc OPENROUTER_API_KEY như .env.example
```

> `openssl rand -hex 32` để sinh `SESSION_SECRET`.

Apply migrations (nếu DB chưa mới):

```bash
php include/migrations/migrate.php
# hoặc nạp tay: for f in include/migrations/*.sql; do mariadb -u bituser -p bitbalance < "$f"; done
```

---

## 3. Build + chạy thử tay (lần đầu)

```bash
( cd server && npm install --omit=dev )
( cd client && npm install && npm run build )   # ra client/dist
( cd server && npm start )                       # nghe http://localhost:3000
```

Mở `http://localhost:3000` trên chính máy CachyOS — phải thấy SPA load và
`/api/health` trả `{ "ok": true }`. Ctrl-C để dừng, sang bước chạy nền.

---

## 4. Chạy nền bằng systemd (tự khởi động lại, sống sau khi logout)

Dùng **user service** (không cần root):

```bash
mkdir -p ~/.config/systemd/user
cp deploy/bitbalance.service ~/.config/systemd/user/bitbalance.service
# Sửa WorkingDirectory trong file đó cho đúng đường dẫn repo của bạn.
nano ~/.config/systemd/user/bitbalance.service

systemctl --user daemon-reload
systemctl --user enable --now bitbalance
loginctl enable-linger $USER          # giữ service chạy cả khi chưa đăng nhập

# Theo dõi log:
journalctl --user -u bitbalance -f
```

---

## 5. Host ra ngoài bằng ngrok (1 tunnel)

```bash
# Cài ngrok (AUR) hoặc tải binary:
# yay -S ngrok            # nếu có AUR helper
ngrok config add-authtoken <NGROK_AUTHTOKEN>   # lấy ở dashboard.ngrok.com
```

Copy config mẫu rồi sửa authtoken:

```bash
mkdir -p ~/.config/ngrok
cp deploy/ngrok.yml ~/.config/ngrok/ngrok.yml
nano ~/.config/ngrok/ngrok.yml          # dán authtoken, (tùy chọn) domain tĩnh
```

Chạy tunnel:

```bash
ngrok start bitbalance     # trỏ tới :3000 (Express phục vụ cả SPA lẫn /api)
```

ngrok in ra URL `https://xxxx.ngrok-free.app` → đó là địa chỉ public của app.

> **Mẹo URL cố định:** vào dashboard.ngrok.com → Domains, claim 1 domain free
> tĩnh, bỏ comment dòng `domain:` trong `ngrok.yml`. URL không đổi mỗi lần
> restart → khỏi sửa lại `GOOGLE_REDIRECT_URI`/`CLIENT_ORIGIN` liên tục.

> **Lưu ý ngrok free:** lần đầu vào có trang cảnh báo "Visit Site" — bấm qua là
> được; không ảnh hưởng API sau đó.

### (Tùy chọn) Cho ngrok chạy nền như systemd

```bash
# Tạo ~/.config/systemd/user/ngrok.service tương tự, ExecStart:
#   /usr/bin/ngrok start bitbalance --config %h/.config/ngrok/ngrok.yml
systemctl --user enable --now ngrok
```

---

## 6. SSH từ xa qua Tailscale (mạng closed-port)

Tailscale là mesh VPN, đục xuyên CGNAT/NAT không cần mở port. Sau khi cài, máy
CachyOS có IP riêng `100.x.y.z` truy cập được từ bất kỳ đâu.

**Trên CachyOS:**

```bash
sudo pacman -S --needed tailscale openssh
sudo systemctl enable --now tailscaled
sudo systemctl enable --now sshd          # bật SSH daemon

# Đăng nhập Tailscale + bật Tailscale SSH (không cần quản lý key thủ công):
sudo tailscale up --ssh
tailscale ip -4                            # in IP 100.x.y.z của máy này
```

**Trên máy của bạn (máy ở xa):** cài Tailscale, đăng nhập **cùng tài khoản**, rồi:

```bash
ssh <user-cachyos>@100.x.y.z      # IP Tailscale; hoặc dùng MagicDNS: ssh <user>@<hostname>
```

Vì cả hai máy ở chung tailnet, kết nối đi qua mạng riêng ảo — không phụ thuộc
việc ISP chặn port. `--ssh` cho phép Tailscale tự xác thực, khỏi copy public key.

> Muốn truy cập **luôn cả web app riêng tư** (không qua ngrok) thì gõ
> `http://100.x.y.z:3000` từ máy trong tailnet.

---

## 7. Deploy từ xa bằng script

Sau khi SSH được vào (qua Tailscale), deploy lại chỉ là một lệnh. Script
`deploy/deploy.sh` làm: fetch + fast-forward nhánh hiện tại → cài deps → build
client → chạy migrations (nếu có php) → restart systemd.

**Cách 1 — SSH vào rồi chạy:**

```bash
ssh <user>@100.x.y.z
cd ~/BitBalance-2.0---Calorie-Tracker
./deploy/deploy.sh
```

**Cách 2 — một dòng từ máy của bạn (không cần vào shell):**

```bash
ssh <user>@100.x.y.z 'cd ~/BitBalance-2.0---Calorie-Tracker && ./deploy/deploy.sh'
```

**Chọn nhánh khác / tên service khác:**

```bash
ssh <user>@100.x.y.z 'cd ~/BitBalance-2.0---Calorie-Tracker && BRANCH=main ./deploy/deploy.sh'
```

Script dùng `git merge --ff-only` — nếu nhánh local lỡ phân kỳ thì nó dừng để
bạn xử lý tay, không tự ghi đè.

---

## 8. Checklist sau deploy

- [ ] `curl -s https://<domain>.ngrok-free.app/api/health` → `{"ok":true,...}`
- [ ] Mở web bằng URL ngrok, đăng nhập được (cookie giữ → `COOKIE_SECURE=true` đúng).
- [ ] Nếu bật Google: redirect URI trong Google Cloud Console khớp domain ngrok.
- [ ] `journalctl --user -u bitbalance -n 50` không có lỗi DB/connection.
- [ ] DB đã apply đủ migrations PT/macro (xem HANDOFF.md mục 2).

---

## Ghi chú quan trọng

- **Session store vẫn là MemoryStore (dev):** mỗi lần restart service → mất
  session đang đăng nhập, nhưng cookie remember-me (30 ngày) tự đăng nhập lại.
  Muốn bền hẳn thì chuyển sang Redis/MySQL store (HANDOFF.md mục 4.3).
- **`client/vite.config.js`** cố ý để uncommitted; production không dùng Vite dev
  nên không sao. `allowedHosts` trong đó chỉ liên quan khi chạy `npm run dev`.
- **Không commit `server/.env`** (đã gitignore). Secrets sống trên máy CachyOS.
- **ngrok free = 1 tunnel/agent.** SSH đi qua Tailscale (không tốn tunnel), nên
  tunnel ngrok duy nhất dành cho web là đủ.
