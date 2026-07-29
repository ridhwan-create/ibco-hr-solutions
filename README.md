# IBCO HR Solutions

Sistem pengurusan sumber manusia berasaskan Laravel, React, Inertia.js dan
database MySQL sedia ada `db_spp`.

## Dashboard

Dashboard memaparkan jumlah rekod aktif bagi setiap modul serta lima rekod
kehadiran dan permohonan cuti terkini. Semua kad ringkasan boleh digunakan
untuk membuka halaman index modul berkaitan.

## Modul index

- Pekerja
- Jawatan
- Kehadiran
- Cuti
- Kerja lebih masa
- Payroll
- Laporan bulanan
- Roles & Permissions

Semua modul index menggunakan carian dan pagination. Modul Jawatan turut
menyediakan tab rekod aktif dan sejarah.

## CRUD pekerja

Super Admin dan HR Admin boleh:

- menambah pekerja;
- mengemas kini semua maklumat asas pekerja;
- memilih jantina, agama, bangsa, status perkahwinan dan status pekerja
  daripada jadual rujukan `db_spp`; dan
- menyahaktifkan pekerja menggunakan `rcd_enable = 0` tanpa memadam rekod
  secara kekal.

ID pekerja dan NRIC diperiksa untuk mengelakkan rekod pendua. Borang
memerlukan pengesahan sebelum data disimpan, manakala Viewer / Manager kekal
dengan akses paparan sahaja.

Semua tindakan tambah, kemas kini dan nyahaktif direkodkan dalam jadual
`audit_logs` pada database sistem, termasuk pengguna, masa, nilai yang berubah,
alamat IP dan user agent.

## CRUD jawatan dan penempatan

Super Admin dan HR Admin boleh menambah penempatan, menukar jawatan serta
menamatkan jawatan aktif. Setiap pertukaran mewujudkan rekod baharu dan menukar
rekod terdahulu kepada `rcd_enable = 0`, supaya sejarah tidak ditimpa. Sistem
turut memastikan hanya satu jawatan aktif bagi seseorang pekerja.

Viewer / Manager boleh melihat jawatan aktif dan sejarah, tetapi tidak menerima
medan gaji, bank atau caruman daripada backend. Profil pekerja memaparkan
jawatan semasa dan keseluruhan sejarah penempatan.

## Audit Trail

Super Admin dan HR Admin boleh:

- melihat tindakan pekerja serta tambah, tukar dan tamat jawatan;
- melihat pengguna, tarikh, masa, alamat IP serta nilai sebelum dan selepas;
- mencari dan menapis audit mengikut pengguna, tindakan dan julat tarikh;
- melihat senarai pekerja yang mempunyai `rcd_enable = 0`; dan
- mengaktifkan semula pekerja tanpa memadam atau mencipta semula rekod lama.

Pengaktifan semula menukar `rcd_enable` kepada `1` dan direkodkan sebagai
`employee.reactivated` dalam audit log.

## Kehadiran geolocation

Employee boleh merakam masuk dan keluar melalui browser telefon. Server
mengira jarak menggunakan koordinat pejabat dan hanya menerima rakaman apabila:

- akaun Employee telah dipautkan kepada rekod pekerja aktif;
- lokasi pejabat yang ditugaskan masih aktif;
- bacaan GPS memenuhi had ketepatan lokasi; dan
- jarak berada dalam radius lokasi (asal 100 meter).

Waktu rasmi datang daripada server. Sistem menyimpan koordinat, ketepatan GPS,
jarak, lokasi pejabat, IP dan user agent hanya semasa butang rakaman ditekan;
tiada penjejakan lokasi berterusan. Akses geolocation memerlukan HTTPS, kecuali
semasa pembangunan melalui `localhost`.

Semua data modul ini disimpan dalam database sistem melalui jadual:

- `office_locations`;
- `employee_user_links`;
- `geo_attendance_records`; dan
- `attendance_adjustments`.

`db_spp` hanya digunakan dengan operasi `SELECT` untuk identiti pekerja.
Migration modul ini tidak mencipta atau mengubah sebarang jadual dalam
`db_spp`. Super Admin mengurus lokasi dan pautan pekerja, manakala HR Admin
boleh menambah atau membetulkan rekod manual dengan alasan wajib. Pembatalan
rekod menggunakan status `cancelled` dan tidak memadam rekod.

## Profil pekerja

Butang **Papar** pada senarai pekerja membuka profil baca sahaja yang
menggabungkan maklumat peribadi, jawatan terkini, jabatan, gaji asas, maklumat
bank serta ringkasan kehadiran, cuti, kerja lebih masa dan payroll. Setiap
bahagian aktiviti memaparkan sehingga lima rekod terkini daripada `db_spp`.

Maklumat gaji, bank, KWSP, PERKESO dan payroll dalam profil hanya dihantar
kepada pengguna yang mempunyai kebenaran melihat payroll.

## Pengurusan cuti lengkap

Employee Self-Service menyediakan permohonan cuti sehari penuh atau separuh
hari, lampiran PDF/JPG/PNG, paparan baki, notifikasi status dan sejarah cuti
asal daripada `db_spp`.

HR Admin dan Super Admin boleh:

- mengurus jenis cuti serta syarat lampiran dan separuh hari;
- menetapkan kelayakan, bawa hadapan dan pelarasan baki setiap pekerja;
- mendaftarkan cuti umum yang dikecualikan daripada pengiraan hari bekerja;
- menetapkan Penyelia / Ketua Jabatan mengikut jabatan;
- memberi kelulusan akhir selepas sokongan penyelia;
- membatalkan cuti diluluskan dengan pemulangan baki automatik;
- melihat kalendar cuti kakitangan; dan
- menapis serta memuat turun laporan CSV.

Baki hanya dipotong selepas kelulusan akhir HR. Potongan dan pemulangan disimpan
dalam `leave_balance_transactions`. Rekod jenis cuti, kelayakan, permohonan,
kelulusan, lampiran dan notifikasi disimpan dalam database sistem. Modul ini
hanya menjalankan `SELECT` pada `db_spp` untuk rujukan pekerja, jabatan dan
sejarah cuti lama.

## Roles & Permissions

Kawalan akses disimpan dalam jadual `users` pada database sistem:

- **Super Admin** — akses penuh termasuk pengurusan pengguna dan role.
- **HR Admin** — akses semua modul HR termasuk CRUD pekerja, payroll dan
  laporan.
- **Penyelia / Ketua Jabatan** — semak permohonan cuti pekerja bagi jabatan
  yang ditetapkan.
- **Viewer / Manager** — akses Dashboard, Pekerja, Jawatan, Kehadiran, Cuti
  dan Kerja Lebih Masa sahaja.
- **Employee** — rakam masuk/keluar dan melihat sejarah kehadiran sendiri.

Akaun boleh memegang lebih daripada satu role. Permission daripada semua role
akan digabungkan, contohnya akaun `Super Admin + Employee` boleh mengurus sistem
dan merakam kehadiran sendiri.

Super Admin juga boleh menggunakan fungsi **Import Pekerja** untuk membaca
pekerja aktif daripada `db_spp` dan mendaftarkan sehingga 200 akaun Employee
pada satu masa. Pekerja yang telah dipautkan tidak akan diimport semula. Jika
e-mel pekerja sepadan dengan akaun sistem sedia ada, role Employee dan pautan
kehadiran akan ditambah tanpa mengubah role atau kata laluan lama. Akaun baharu
menerima kata laluan rawak yang hanya dipaparkan sekali selepas import dan
boleh dimuat turun sebagai CSV.

Akaun sedia ada paling awal akan menjadi Super Admin apabila migration role
dijalankan. Pengguna baharu menerima Employee secara lalai, kecuali
pengguna pertama bagi pemasangan kosong yang akan menjadi Super Admin.

## Sambungan database

Projek menggunakan dua sambungan MySQL. Database utama menyimpan jadual
Laravel seperti pengguna, sesi, cache dan queue. Connection `ibco` membaca
data HR lama daripada `db_spp`.

Salin `.env.example` kepada `.env`, kemudian isi maklumat kedua-dua sambungan:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ibco-hr-solutions
DB_USERNAME=root
DB_PASSWORD=

DB_CONNECTION_IBCO=mysql
DB_HOST_IBCO=127.0.0.1
DB_PORT_IBCO=3306
DB_DATABASE_IBCO=db_spp
DB_USERNAME_IBCO=root
DB_PASSWORD_IBCO=
```

Selepas mengubah `.env`, jalankan `php artisan optimize:clear`. Jalankan
`php artisan migrate` untuk menyediakan role, audit log dan jadual kehadiran
geolocation pada database sistem. Gunakan akaun database dengan akses minimum
yang diperlukan.
Connection `ibco` memerlukan akses `SELECT`, `INSERT` dan `UPDATE` untuk CRUD
pekerja serta jawatan; operasi `DELETE` tidak digunakan.

Jika fungsi CRUD pekerja dan jawatan lama tidak akan digunakan, connection
`ibco` boleh diberikan akses `SELECT` sahaja. Modul kehadiran geolocation tidak
memerlukan akses tulis kepada `db_spp` dalam apa-apa keadaan.
