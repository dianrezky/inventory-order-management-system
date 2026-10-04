# Command Runner — Demo Docker

Hanya perintah, urut sesuai [`DOCKER-PRESENTATION.md`](DOCKER-PRESENTATION.md). Jalankan dari root proyek, PowerShell.

## 1. Cek Docker terpasang

```powershell
docker --version
docker compose version
docker ps
```

## 2. Build, run, cek status, log (langsung dari Dockerfile)

```powershell
docker build -t iom-app:demo .

docker run -d --name iom_demo -p 8091:8080 `
  --network inventory-order-management-system_iom_net `
  -v "${PWD}:/var/www/html" `
  -e DB_HOST=db -e DB_PORT=3306 -e DB_NAME=inventory_order_management `
  -e DB_USER=iom_app -e DB_PASSWORD=change_me_in_local_env `
  -e REDIS_HOST=redis -e MEMCACHED_HOST=memcached `
  iom-app:demo

docker ps --filter name=iom_demo

docker logs iom_demo | Select-String -Pattern "fatal" -CaseSensitive:$false
```

Buka browser: `http://localhost:8091/login`

```powershell
docker rm -f iom_demo
```

## 3. Jalankan dengan Docker Compose

```powershell
docker compose up --build -d
docker compose ps
```

Buka browser: `http://localhost:8090`

## 4. Bukti koneksi app → database

```powershell
docker exec iom_app php -r '$p=new PDO(\"mysql:host=db;dbname=\".getenv(\"DB_NAME\"),getenv(\"DB_USER\"),getenv(\"DB_PASSWORD\")); echo \"tables=\".$p->query(\"show tables\")->rowCount();'
```

## 5. Bukti volume persisten (opsional, BAGIAN B)

```powershell
docker volume ls
```

## 6. Bersihkan setelah demo (opsional)

```powershell
docker compose down
```
