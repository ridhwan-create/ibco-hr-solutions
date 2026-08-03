# IBCO HR Solutions

Sistem pengurusan sumber manusia berasaskan Laravel, React, Inertia.js dan
database MySQL sedia ada `db_spp`.

## Dashboard

Dashboard memaparkan jumlah rekod aktif bagi setiap modul serta lima rekod
kehadiran dan permohonan cuti terkini. HR Admin dan Super Admin turut menerima
ringkasan eksekutif bulan semasa bagi kadar kehadiran, cuti, OT, gaji bersih
dan permohonan yang masih menunggu tindakan. Semua kad ringkasan boleh
digunakan untuk membuka halaman modul berkaitan.

## Modul index

- Pekerja
- Jawatan
- Jadual kerja, syif & roster
- Prestasi, KPI & penilaian tahunan
- Pengambilan pekerja & onboarding
- Latihan & kompetensi
- Dokumen & surat HR
- Disiplin & aduan
- Berhenti kerja & clearance
- Kehadiran
- Cuti
- Kerja lebih masa
- Tuntutan & bayaran balik
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
memerlukan pengesahan sebelum data disimpan, manakala Viewer / Pemerhati kekal
dengan akses paparan sahaja.

Semua tindakan tambah, kemas kini dan nyahaktif direkodkan dalam jadual
`audit_logs` pada database sistem, termasuk pengguna, masa, nilai yang berubah,
alamat IP dan user agent.

## CRUD jawatan dan penempatan

Super Admin dan HR Admin boleh menambah penempatan, menukar jawatan serta
menamatkan jawatan aktif. Setiap pertukaran mewujudkan rekod baharu dan menukar
rekod terdahulu kepada `rcd_enable = 0`, supaya sejarah tidak ditimpa. Sistem
turut memastikan hanya satu jawatan aktif bagi seseorang pekerja.

Viewer / Pemerhati boleh melihat jawatan aktif dan sejarah, tetapi tidak menerima
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

## Jadual Kerja, Syif & Roster

Modul roster menyediakan template waktu pejabat, syif pagi, petang dan malam
yang boleh merentas tengah malam. HR boleh menetapkan template mengikut
pekerja, jabatan atau lokasi dengan tarikh kuat kuasa serta keutamaan. Jadual
bulanan dijana sebagai Draf sebelum diterbitkan kepada Employee dan akhirnya
dikunci.

Fungsi utama merangkumi:

- jadual lima atau enam hari bekerja dan tempoh rehat;
- cuti umum, hari rehat dan hari tidak bekerja;
- kelonggaran lewat hadir serta pulang awal;
- roster bulanan mengikut pekerja, jabatan dan lokasi;
- pelarasan manual bersebab semasa status Draf;
- pertukaran syif dengan kelulusan penyelia;
- notifikasi penerbitan serta keputusan pertukaran;
- pengesanan lewat, pulang awal dan tidak hadir;
- eksport CSV dan Audit Trail; dan
- paparan **Jadual Saya** untuk Employee.

Roster yang telah diterbitkan menjadi snapshot rasmi bagi Kehadiran, OT,
potongan cuti tanpa gaji, Payroll dan Laporan Bulanan. Pertukaran masih boleh
diluluskan sebelum roster dikunci. Semua transaksi roster disimpan dalam
database aplikasi dan `db_spp` hanya digunakan melalui operasi `SELECT` untuk
rujukan pekerja serta jabatan.

## Prestasi, KPI & Penilaian Tahunan

Modul prestasi menyediakan kitaran tahunan, setengah tahun dan tempoh
percubaan. HR membina template KPI mengikut jabatan atau jawatan dengan jumlah
pemberat wajib 100%. Semasa penilaian dijana, sasaran disalin sebagai snapshot
supaya perubahan template tidak mengubah rekod penilaian terdahulu.

Aliran penilaian ialah:

1. Employee melengkapkan Self-Assessment, skor kendiri dan bukti pencapaian.
2. Penyelia menilai setiap KPI serta merekodkan kekuatan, ruang
   penambahbaikan dan pelan pembangunan.
3. HR menjalankan moderasi skor dan memberi ulasan akhir.
4. HR/Super Admin memuktamadkan keputusan dan rating.

Modul turut menyediakan penjanaan penilaian pukal, skala rating boleh
dikonfigurasi, dashboard jabatan, peringatan tindakan, sejarah prestasi
Employee, eksport CSV, laporan PDF persendirian dan Audit Trail. HR boleh
membuka Performance Improvement Plan (PIP), menetapkan sasaran peningkatan,
tempoh, sokongan serta merekodkan semakan kemajuan.

Semua bukti disimpan dalam storan persendirian dan hanya boleh dimuat turun
oleh pemilik, penyelia yang ditetapkan atau HR. Data pekerja, jawatan dan
jabatan daripada `db_spp` digunakan melalui `SELECT` sahaja; semua transaksi
prestasi disimpan dalam database aplikasi.

## Pengambilan Pekerja & Onboarding

Modul pengambilan mengurus keseluruhan perjalanan calon bermula daripada
permohonan kekosongan sehingga lapor diri:

- kekosongan Draf → Menunggu Kelulusan → Diluluskan → Dibuka → Ditutup;
- pipeline calon bagi saringan, senarai pendek, temu duga, tawaran dan
  pengambilan;
- resume, sijil dan dokumen calon dalam storan persendirian;
- temu duga berbilang pusingan dengan panel serta scorecard individu;
- tawaran Draf → Kelulusan → Dihantar → Diterima/Ditolak;
- penjanaan checklist onboarding secara automatik daripada tawaran diterima;
- template onboarding mengikut jabatan atau jawatan;
- tugasan HR, Penyelia, ICT, Kewangan, Fasiliti dan Pekerja Baharu;
- paparan **Onboarding Saya**, kemajuan, tarikh akhir dan tugasan lewat;
- pautan calon berjaya kepada rekod pekerja aktif, akaun Employee serta lokasi
  pejabat;
- notifikasi dalaman, eksport CSV dan Audit Trail.

Semasa memautkan calon, sistem hanya memilih rekod pekerja sedia ada daripada
`db_spp` dan menyimpan pautan dalam database aplikasi. Modul pengambilan dan
onboarding tidak menambah atau mengubah jadual `db_spp`.

## Latihan & Kompetensi

Modul ini menghubungkan pembangunan pekerja dengan KPI, PIP, onboarding dan
jurang kompetensi melalui aliran **Permohonan → Penyelia → HR → Pendaftaran →
Kehadiran/Keputusan → Sijil & Penilaian**.

Fungsi utama merangkumi:

- katalog kursus, penyedia, kaedah penyampaian, mata CPD dan tempoh sah sijil;
- sesi latihan dengan tarikh tutup, kapasiti, fasilitator, lokasi dan kos;
- bajet tahunan umum atau mengikut jabatan serta kawalan baki semasa kelulusan;
- permohonan Employee, pencalonan HR dan penyelia kelulusan mengikut jabatan;
- rekod kehadiran, jam latihan, skor, keputusan dan maklum balas keberkesanan;
- sijil PDF/JPG/PNG dalam storan persendirian dan tarikh luput;
- kerangka kompetensi berskala, keperluan mengikut jabatan/jawatan dan penilaian
  tahap semasa;
- matriks jurang kompetensi serta pelan pembangunan individu;
- pautan pelan kepada KPI, Performance Improvement Plan (PIP), onboarding atau
  jurang kompetensi;
- paparan **Latihan Saya**, notifikasi tindakan, eksport CSV dan Audit Trail.

Semua transaksi latihan, sijil dan kompetensi disimpan dalam database aplikasi.
Identiti, jawatan serta jabatan pekerja daripada `db_spp` hanya dibaca melalui
operasi `SELECT`; migration modul ini tidak mengubah database asal.

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

## Pengurusan kerja lebih masa lengkap

Employee boleh memohon OT, memuat naik lampiran, melihat padanan dengan rekod
kehadiran geolocation dan menerima notifikasi keputusan. Permohonan menggunakan
kelulusan dua peringkat Penyelia → HR. HR mengesahkan minit akhir yang
diluluskan, manakala jenis OT menyimpan kadar gandaan serta had minimum dan
maksimum.

Jam OT diluluskan kekal dalam database sistem sebagai input Payroll Core.
Rekod OT lama daripada `db_spp` dipaparkan secara berasingan sebagai rujukan dan
tidak diubah oleh aliran baharu.

## Pengurusan tuntutan & bayaran balik

Employee boleh memohon tuntutan perjalanan, petrol, tol, parkir, makan,
perubatan atau kategori lain serta memuat naik sehingga lima resit PDF/JPG/PNG.
Modul ini menyediakan:

- had setiap tuntutan, bulanan dan tahunan mengikut jenis;
- had khas mengikut pekerja atau rekod jawatan aktif;
- pengesanan nombor resit dan tuntutan pendua;
- kelulusan dua peringkat Penyelia → HR/Kewangan;
- pelarasan amaun diluluskan dengan catatan;
- badge serta pusat notifikasi mengikut peringkat tindakan;
- penjadualan tuntutan diluluskan ke payroll bulan terpilih;
- laporan CSV, lampiran persendirian dan Audit Trail; dan
- sejarah serta status bayaran dalam **Tuntutan Saya**.

Bayaran balik dimasukkan ke Payroll Core sebagai item pendapatan yang tidak
dianggap upah KWSP, PERKESO, EIS atau PCB. Tuntutan hanya ditanda dibayar
apabila payroll berkaitan dimuktamadkan. Payroll yang telah melepasi status
Draf tidak boleh menerima tuntutan baharu. Semua transaksi tuntutan disimpan
dalam database aplikasi dan hanya menjalankan `SELECT` pada `db_spp`.

## Payroll Core

Payroll Core menyimpan semua konfigurasi dan transaksi baharu dalam database
aplikasi. Modul ini menyediakan:

- profil gaji asas pekerja dengan bulan berkuat kuasa;
- komponen pendapatan dan potongan tetap;
- formula hari bekerja dan jam sehari yang boleh dikonfigurasi;
- pengiraan automatik OT yang telah diluluskan;
- potongan cuti tanpa gaji yang telah diluluskan;
- pelarasan pendapatan atau potongan secara manual semasa Draf;
- penjanaan payroll bulanan dan laporan CSV;
- aliran Draf → Semakan HR → Kelulusan → Dimuktamadkan; dan
- snapshot bulanan serta Audit Trail supaya payroll lama tidak berubah apabila
  tetapan gaji dikemas kini.

HR Admin menyediakan, mengira semula dan menyemak payroll. Super Admin bertindak
sebagai pelulus serta memuktamadkan payroll. Payroll yang dimuktamadkan dikunci
daripada pengiraan semula dan pelarasan. Payroll lama dalam `db_spp` kekal
tersedia melalui **Payroll Asal**.

Formula lalai menggunakan 26 hari bekerja dan 8 jam sehari. Kadar OT seminit
dikira daripada gaji asas dan didarab dengan minit serta kadar jenis OT yang
diluluskan. Formula serta semua transaksi Payroll Core hanya menjalankan
`SELECT` pada `db_spp`; tiada data payroll baharu ditulis ke database lama.

## Statutori & Slip Gaji PDF

Fasa kedua Payroll menambah profil dan snapshot statutori bagi setiap pekerja:

- KWSP mengikut kategori pekerja, umur, kewarganegaraan dan jadual julat upah;
- PERKESO Kategori Pertama/Kedua termasuk caruman SKBBK berkuat kuasa Jun 2026;
- EIS dengan siling upah serta bahagian pekerja dan majikan;
- PCB bulanan yang disahkan HR melalui kaedah atau kalkulator rasmi LHDN;
- pelarasan statutori bersebab semasa payroll masih Draf;
- caruman pekerja sebagai potongan gaji dan caruman majikan sebagai kos
  berasingan;
- laporan CSV dengan pecahan caruman; dan
- slip gaji PDF yang memaparkan pendapatan, potongan, caruman serta gaji bersih.

Nilai statutori disimpan pada `payroll_statutory_snapshots`. Perubahan kadar
kemudian tidak mengubah payroll atau slip lama. Employee hanya boleh membuka
**Slip Gaji Saya** bagi rekod sendiri selepas payroll dimuktamadkan. HR dan
Super Admin boleh memuat turun PDF pekerja melalui perincian payroll.

Kadar lalai merujuk versi yang berkuat kuasa pada tarikh pembangunan, tetapi HR
perlu mengesahkannya terhadap jadual rasmi sebelum setiap perubahan kadar.
PCB tidak dianggap sebagai peratus tetap kerana amaun sebenar bergantung pada
data cukai individu, TP1, TP3 dan saraan tambahan.

## Laporan Bulanan & Dashboard Eksekutif

Laporan operasi baharu menggabungkan data pekerja, kehadiran geolocation, cuti,
OT dan snapshot payroll mengikut bulan. HR Admin dan Super Admin boleh:

- menapis laporan mengikut bulan, jabatan dan lokasi pejabat;
- melihat kadar kehadiran, purata jam bekerja dan rekod keluar tidak lengkap;
- melihat hari cuti dan jam OT yang telah diluluskan;
- melihat status payroll, gaji kasar, potongan, gaji bersih dan caruman majikan;
- membandingkan trend enam bulan;
- membandingkan prestasi mengikut jabatan;
- melihat perkara yang memerlukan perhatian pengurusan; dan
- mengeksport ringkasan, pecahan jabatan dan trend dalam CSV.

Halaman **Laporan Asal** mengekalkan rekod `reportbulanan` daripada `db_spp`
sebagai rujukan. Penjanaan laporan baharu hanya menjalankan `SELECT` pada
`db_spp`; eksport direkodkan dalam Audit Trail pada database aplikasi.

## Disiplin & Aduan

Modul ini menyediakan saluran sulit berasaskan prinsip **need-to-know** dengan
aliran **Aduan → Triage → Siasatan → Tunjuk Sebab → Keputusan → Rayuan →
Penutupan**.

Fungsi utama merangkumi:

- aduan bernama atau identiti dilindungi dengan kategori, tahap risiko dan SLA;
- sehingga lima bukti awal PDF, imej atau dokumen dalam storan persendirian;
- snapshot pengadu, subjek, jabatan dan jawatan supaya rekod kes lama kekal;
- triage HR, pelantikan penyiasat/panel dan sasaran tarikh penyelesaian;
- deklarasi konflik kepentingan wajib sebelum pegawai membuka butiran siasatan;
- pengguguran automatik pegawai yang mengisytiharkan konflik;
- kronologi siasatan, temu bual, kenyataan dan bukti dengan kawalan keterlihatan;
- dapatan terbukti, sebahagian terbukti, tidak terbukti atau tidak konklusif;
- surat tunjuk sebab, representasi pekerja dan draf tindakan melalui modul
  **Dokumen & Surat HR**;
- pemisahan HR, penyiasat, pembuat keputusan dan panel rayuan bebas;
- tempoh rayuan boleh dikonfigurasi, keputusan rayuan dan penutupan kes;
- paparan **Aduan Saya**, notifikasi tindakan, badge menu, CSV dan Audit Trail.

Responden tidak menerima identiti pengadu yang dilindungi. Pegawai siasatan
tidak boleh bertindak sebelum deklarasi konflik selesai, manakala pelulus
keputusan asal tidak boleh menyemak rayuan kes yang sama. Kandungan aduan,
kenyataan dan bukti tidak disalin ke Audit Trail; hanya metadata tindakan yang
direkodkan.

Semua transaksi disiplin disimpan dalam database aplikasi. Identiti pekerja,
jawatan dan jabatan daripada `db_spp` hanya dibaca melalui operasi `SELECT`.

## Berhenti Kerja & Clearance

Modul ini mengurus kitaran **Notis/Draf HR → Kelulusan Penyelia → Kelulusan HR
→ Clearance → Semakan Akhir → Selesai** untuk berhenti sukarela, tamat kontrak,
persaraan, penamatan dan jenis pengakhiran lain.

Fungsi utama merangkumi:

- template proses mengikut jenis pengakhiran dan tempoh notis minimum;
- notis berhenti Employee serta pembukaan kes oleh HR;
- snapshot pekerja, jawatan dan jabatan bagi mengekalkan rekod sejarah;
- checklist clearance Employee, Penyelia, ICT, Pentadbiran, Finance, Payroll
  dan HR dengan pegawai, tarikh akhir serta bukti persendirian;
- rekod pemulangan aset, keadaan aset dan caj yang diselaraskan ke final
  settlement;
- serahan tugas kepada penerima yang ditetapkan dengan semakan dan pemulangan;
- exit interview berjadual serta maklum balas Employee;
- final settlement, pengesahan berasingan dan jumlah bersih;
- surat penerimaan notis serta surat pelepasan melalui modul Dokumen & Surat HR;
- sekatan penutupan sehingga semua item wajib lengkap;
- notifikasi tindakan, badge menu, eksport CSV dan Audit Trail.

HR tidak boleh meluluskan kes yang dibukanya sendiri, dan penyedia final
settlement tidak boleh mengesahkan pengiraan sendiri. Modul tidak menyahaktifkan
rekod pekerja secara automatik; perubahan status pada sistem asal hendaklah
melalui proses pentadbiran pekerja yang berasingan. Semua transaksi pengakhiran
disimpan dalam database aplikasi, manakala data pekerja daripada `db_spp` hanya
dibaca melalui operasi `SELECT`.

## Roles & Permissions

Kawalan akses disimpan dalam jadual `users` pada database sistem:

- **Super Admin** — akses teknikal penuh kepada modul, pengguna, role,
  konfigurasi, keselamatan dan Audit Trail, tanpa kuasa meluluskan transaksi HR.
- **Pengurus HR** — pelulus akhir bagi proses HR termasuk pengambilan,
  onboarding, cuti, OT, tuntutan, roster, prestasi, latihan, dokumen, tatatertib,
  penggajian dan pelepasan pekerja.
- **HR Admin** — akses semua modul HR termasuk disiplin, pengambilan,
  onboarding, pengakhiran dan clearance, CRUD pekerja, roster, prestasi,
  penyediaan tuntutan, Payroll Core dan laporan. HR menyediakan, memproses dan
  menyemak transaksi tetapi tidak membuat kelulusan akhir.
- **Penyelia / Ketua Jabatan** — menyertai panel temu duga dan pasukan siasatan
  yang ditetapkan serta menyemak onboarding, cuti, OT, tuntutan, pertukaran
  syif dan penilaian prestasi pekerja.
- **Viewer / Pemerhati** — akses Dashboard, Pekerja, Jawatan, Kehadiran, Cuti
  dan Kerja Lebih Masa sahaja.
- **Employee** — melihat onboarding, roster dan prestasi, menghantar aduan
  sulit, menjawab tunjuk sebab, membuat rayuan, memohon pertukaran syif,
  merakam masuk/keluar serta mengurus profil, cuti, OT, tuntutan dan slip gaji
  sendiri.

Akaun boleh memegang lebih daripada satu role. Permission daripada semua role
akan digabungkan, contohnya akaun `Super Admin + Employee` boleh mengurus sistem
dan merakam kehadiran sendiri. Role Pengurus HR tidak boleh digabungkan dengan
Super Admin, HR Admin atau Penyelia supaya pengasingan tugas kekal berkesan.

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
Modul ESS, cuti, OT, roster, prestasi, pengambilan, onboarding, latihan,
kompetensi, dokumen, disiplin, pengakhiran, clearance, tuntutan, Payroll Core,
statutori dan slip gaji juga hanya memerlukan akses `SELECT` kepada `db_spp`.
