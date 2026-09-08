/**
 * Dinamik Mobil QR Tabanlı PDKS Terminali
 * Kapı Terminali Kamera Tarayıcı ve Canlı Backend Entegrasyon Scripti
 */

let html5QrCode = null;
let isScanning = false;
let isProcessing = false;
let currentCameraId = null;
let availableCameras = [];
let soundEnabled = true;

// Web Audio API ile Tarama Bip Sesi
function playBeep(isSuccess = true) {
    if (!soundEnabled) return;
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();

        osc.type = isSuccess ? 'sine' : 'sawtooth';
        osc.frequency.setValueAtTime(isSuccess ? 880 : 220, audioCtx.currentTime);
        if (isSuccess) {
            osc.frequency.exponentialRampToValueAtTime(1320, audioCtx.currentTime + 0.12);
        }

        gainNode.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.2);

        osc.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        osc.start();
        osc.stop(audioCtx.currentTime + 0.22);
    } catch (e) {
        console.warn('Audio Context başlatılamadı:', e);
    }
}

// Canlı Saat ve Tarih
function updateKioskClock() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const dateStr = now.toLocaleDateString('tr-TR', { day: '2-digit', month: 'long', year: 'numeric', weekday: 'long' });

    const clockEl = document.getElementById('kiosk-live-clock');
    const dateEl = document.getElementById('kiosk-live-date');
    if (clockEl) clockEl.textContent = timeStr;
    if (dateEl) dateEl.textContent = dateStr;
}

// Veritabanından Son Geçişleri Getir
async function fetchRecentPasses() {
    try {
        const response = await fetch('api/scan.php?action=recent_passes');
        const result = await response.json();

        if (result.status && Array.isArray(result.data)) {
            renderRecentPasses(result.data, result.total_today || 0);
        }
    } catch (err) {
        console.warn('Son geçişler yüklenirken hata oluştu:', err);
    }
}

// Son Geçiş Yapanlar Listesini Render Et
function renderRecentPasses(passes, totalCount) {
    const feedContainer = document.getElementById('recent-passes-feed');
    if (!feedContainer) return;

    feedContainer.innerHTML = '';
    const badgeEl = document.getElementById('pass-count-badge');
    if (badgeEl) {
        badgeEl.textContent = `Bugün: ${totalCount} Geçiş`;
    }

    if (!passes || passes.length === 0) {
        feedContainer.innerHTML = `
            <div class="text-center py-5 text-secondary" id="empty-feed-msg">
                <i class="fa-solid fa-clock fs-2 mb-2 d-block opacity-40"></i>
                Henüz kayıtlı geçiş hareketi bulunmuyor.<br>
                <small class="text-muted">Turnikeden okutulan QR kodlar burada anlık listelenecektir.</small>
            </div>
        `;
        return;
    }

    passes.forEach((item) => {
        const isEntry = item.islem_turu === 'giris';
        const card = document.createElement('div');
        card.className = 'feed-card';
        card.innerHTML = `
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-feed-placeholder">${item.ad_soyad.charAt(0)}</div>
                <div>
                    <div class="fw-bold text-white fs-6 mb-0">${item.ad_soyad}</div>
                    <div class="text-secondary small">${item.departman || 'Genel'} • <span class="font-monospace">${item.eposta || ''}</span></div>
                </div>
            </div>
            <div class="text-end">
                <span class="${isEntry ? 'badge-entry' : 'badge-exit'} mb-1 d-inline-flex align-items-center gap-1">
                    <i class="fa-solid ${isEntry ? 'fa-arrow-right-to-bracket' : 'fa-arrow-right-from-bracket'}"></i>
                    ${isEntry ? 'GİRİŞ' : 'ÇIKIŞ'}
                </span>
                <div class="text-secondary small font-monospace fw-bold">${item.islem_saati}</div>
            </div>
        `;
        feedContainer.appendChild(card);
    });
}

// Ön İzin Alarak Kamera İsimlerinin ve Listesinin Doldurulmasını Sağla
async function requestCameraPermissions() {
    try {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            const tempStream = await navigator.mediaDevices.getUserMedia({ video: true });
            tempStream.getTracks().forEach(track => track.stop());
        }
    } catch (e) {
        console.warn("Ön kamera izni alma uyarısı:", e);
    }
}

// Kamera Seçim Açılır Menüsünü Doldur
function populateCameraDropdown() {
    const dropdown = document.getElementById('camera-select-dropdown');
    if (!dropdown || !availableCameras || availableCameras.length === 0) return;

    dropdown.innerHTML = '';
    availableCameras.forEach((cam, index) => {
        const option = document.createElement('option');
        option.value = cam.id;
        option.textContent = cam.label || `Kamera ${index + 1}`;
        if (cam.id === currentCameraId) {
            option.selected = true;
        }
        dropdown.appendChild(option);
    });

    // Açılır menü değiştiğinde kamerayı yenile
    dropdown.onchange = async (e) => {
        const targetId = e.target.value;
        if (targetId && targetId !== currentCameraId) {
            await switchCameraToId(targetId);
        }
    };
}

// Belirli bir kameraya geçiş yap
async function switchCameraToId(targetCameraId) {
    updateScannerStatus(false, "Kamera Değiştiriliyor...");
    
    // Var olan tarayıcıyı güvenli şekilde durdur
    try {
        if (html5QrCode && isScanning) {
            await html5QrCode.stop();
        }
    } catch (e) {
        console.warn("Kamera durdurma uyarısı:", e);
    }
    isScanning = false;

    // DOM alanını temizle ve yeni Html5Qrcode örneği oluştur
    const readerElement = document.getElementById('terminal-reader');
    if (readerElement) {
        readerElement.innerHTML = '';
    }

    try {
        html5QrCode = new Html5Qrcode("terminal-reader");
        currentCameraId = targetCameraId;

        const config = {
            fps: 15,
            qrbox: { width: 280, height: 280 },
            aspectRatio: 1.0,
            showTorchButtonIfSupported: true
        };

        await html5QrCode.start(
            currentCameraId,
            config,
            (decodedText) => processQrText(decodedText),
            () => {}
        );

        isScanning = true;
        updateScannerStatus(true, "Kamera Aktif • QR Kodu Çerçeveye Hizalayın");
        
        const dropdown = document.getElementById('camera-select-dropdown');
        if (dropdown) dropdown.value = currentCameraId;

    } catch (err) {
        console.error("Kamera geçiş hatası:", err);
        showCameraError("Seçilen kamera başlatılamadı. Lütfen menüden başka bir kamera seçin.");
    }
}

// Kamerayı Başlat
async function startCameraScanner() {
    const readerElement = document.getElementById('terminal-reader');
    if (!readerElement) return;

    try {
        // İzinleri almak ve isimlerin doğru okunmasını sağlamak için ön izin iste
        await requestCameraPermissions();

        availableCameras = await Html5Qrcode.getCameras();

        if (!availableCameras || availableCameras.length === 0) {
            showCameraError("Cihaza bağlı herhangi bir kamera bulunamadı.");
            return;
        }

        // Mac Desk View (Masa Görünümü) kamerasını eleyerek varsayılan web kamerasını seç (FaceTime HD / Ön Kamera)
        let preferredCam = availableCameras.find(c => {
            const label = (c.label || '').toLowerCase();
            return !label.includes('desk view') && 
                   !label.includes('masa görünümü') && 
                   !label.includes('deskview');
        });

        currentCameraId = preferredCam ? preferredCam.id : availableCameras[0].id;
        populateCameraDropdown();

        // Seçilen kamerayı başlat
        await switchCameraToId(currentCameraId);

    } catch (err) {
        console.error("Kamera başlatma hatası:", err);
        showCameraError("Kamera izni verilmedi veya tarayıcı kısıtlaması nedeniyle başlatılamadı.");
    }
}

// QR Kod Doğrulama ve Backend Kayıt
async function processQrText(qrData) {
    if (isProcessing) return;

    isProcessing = true;
    updateScannerStatus(false, "Doğrulanıyor...");
    console.log("🔍 Taranan QR Kod:", qrData);

    try {
        const response = await fetch('api/scan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                qr_data: qrData,
                device_info: 'Turnike #01'
            })
        });

        const result = await response.json();

        if (result.status && result.data) {
            playBeep(true);
            fetchRecentPasses();

            const isEntry = result.data.islem_turu === 'giris';

            Swal.fire({
                icon: 'success',
                title: isEntry ? 'Hoş Geldiniz' : 'İyi Günler',
                html: `
                    <div class="text-center py-2">
                        <div class="avatar-feed-placeholder mx-auto mb-3" style="width: 70px; height: 70px; font-size: 1.8rem;">
                            ${result.data.ad_soyad.charAt(0)}
                        </div>
                        <h4 class="fw-bold text-white mb-1">${result.data.ad_soyad}</h4>
                        <div class="text-info fw-semibold mb-2">${result.data.departman}</div>
                        <div class="badge ${isEntry ? 'bg-success' : 'bg-danger'} fs-6 px-3 py-2">
                            <i class="fa-solid ${isEntry ? 'fa-arrow-right-to-bracket' : 'fa-arrow-right-from-bracket'} me-1"></i>
                            ${isEntry ? 'GİRİŞ ONAYLANDI' : 'ÇIKIŞ ONAYLANDI'} • ${result.data.islem_saati}
                        </div>
                    </div>
                `,
                timer: 2500,
                timerProgressBar: true,
                showConfirmButton: false,
                background: '#0d1527',
                color: '#f8fafc',
                backdrop: 'rgba(0, 0, 0, 0.65)'
            }).then(() => {
                isProcessing = false;
                updateScannerStatus(true, "Kamera Aktif • Telefonunuzdaki QR Kodu Okutun");
            });

        } else {
            playBeep(false);

            Swal.fire({
                icon: 'error',
                title: 'Geçersiz / Yetkisiz QR Kod',
                text: result.message || 'Kullanıcı doğrulanamadı.',
                timer: 2500,
                timerProgressBar: true,
                showConfirmButton: false,
                background: '#0d1527',
                color: '#f8fafc'
            }).then(() => {
                isProcessing = false;
                updateScannerStatus(true, "Kamera Aktif • Telefonunuzdaki QR Kodu Okutun");
            });
        }

    } catch (err) {
        console.error("API Bağlantı Hatası:", err);
        playBeep(false);

        Swal.fire({
            icon: 'warning',
            title: 'Sunucu Hatası',
            text: 'Geçiş sunucuya iletilemedi.',
            timer: 2500,
            showConfirmButton: false,
            background: '#0d1527',
            color: '#f8fafc'
        }).then(() => {
            isProcessing = false;
            updateScannerStatus(true, "Kamera Aktif • Telefonunuzdaki QR Kodu Okutun");
        });
    }
}

// Durum Metnini Güncelle
function updateScannerStatus(isActive, message) {
    const statusText = document.getElementById('scanner-status-text');
    const statusPill = document.getElementById('scanner-status');
    if (statusText) statusText.textContent = message;
    if (statusPill) {
        statusPill.style.borderColor = isActive ? 'rgba(6, 182, 212, 0.4)' : 'rgba(245, 158, 11, 0.5)';
    }
}

// Kamera Hata Ekranı
function showCameraError(msg) {
    const box = document.getElementById('scanner-box');
    if (box) {
        box.innerHTML = `
            <div class="text-center p-4 text-secondary">
                <i class="fa-solid fa-video-slash text-danger fs-1 mb-3"></i>
                <h5 class="text-white fw-bold">Kamera Açılamadı</h5>
                <p class="small text-muted mb-3">${msg}</p>
                <button class="btn btn-outline-info btn-sm" onclick="location.reload()">
                    <i class="fa-solid fa-rotate-right me-1"></i> Yeniden Dene
                </button>
            </div>
        `;
    }
}

// Kamera Değiştir
async function switchCamera() {
    if (!availableCameras || availableCameras.length < 2) {
        Swal.fire({
            icon: 'info',
            title: 'Kamera Değiştirilemedi',
            text: 'Cihazda geçiş yapılabilecek başka bir kamera bulunamadı.',
            timer: 1800,
            showConfirmButton: false,
            background: '#0d1527',
            color: '#fff'
        });
        return;
    }

    const currentIndex = availableCameras.findIndex(c => c.id === currentCameraId);
    const nextIndex = (currentIndex + 1) % availableCameras.length;
    await switchCameraToId(availableCameras[nextIndex].id);
}

// Tam Ekran Modu
function toggleFullScreen() {
    const icon = document.getElementById('fullscreen-icon');
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => console.warn(err));
        if (icon) icon.className = 'fa-solid fa-compress';
    } else {
        if (document.exitFullscreen) document.exitFullscreen();
        if (icon) icon.className = 'fa-solid fa-expand';
    }
}

// Ses Kontrolü
function toggleAudio() {
    soundEnabled = !soundEnabled;
    const icon = document.getElementById('sound-icon');
    if (icon) {
        icon.className = soundEnabled ? 'fa-solid fa-volume-high text-success' : 'fa-solid fa-volume-xmark text-danger';
    }
}

// Sayfa Başlatıldığında
document.addEventListener('DOMContentLoaded', () => {
    updateKioskClock();
    setInterval(updateKioskClock, 1000);
    fetchRecentPasses();
    startCameraScanner();
});
