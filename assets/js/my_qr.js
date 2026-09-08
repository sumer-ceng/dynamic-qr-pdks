/**
 * Dinamik Mobil QR Tabanlı PDKS Terminali - İstemci Modülü
 * İsteğe Bağlı Giriş/Çıkış QR Akışı & Turnike Geçişinde Otomatik Kapanma
 */

(function () {
    'use strict';

    // Dinamik Kullanıcı Bilgisi
    const currentUser = (typeof window !== 'undefined' && window.CURRENT_USER) ? window.CURRENT_USER : {
        id: 2,
        empId: 'PER-0002',
        name: 'Ahmet Yılmaz',
        dept: 'Yazılım & AR-GE',
        initials: 'AY',
        initialInside: false,
        lastPassId: 0,
        lastActionTime: null
    };

    // Durum Değişkenleri
    let isQrActive = false;
    let isInside = Boolean(currentUser.initialInside);
    let knownPassId = Number(currentUser.lastPassId) || 0;
    
    const WINDOW_DURATION_MS = 10000; // 10 saniyelik TOTP penceresi
    let remainingTimeMs = WINDOW_DURATION_MS;
    let timerId = null;
    let watermarkTimerId = null;
    let statusPollTimerId = null;
    let qrCodeInstance = null;
    let isFetchingToken = false;

    // DOM Elementleri
    const promptSectionEl = document.getElementById('qr-action-prompt');
    const qrDisplaySectionEl = document.getElementById('qr-display-container');
    const promptTitleEl = document.getElementById('prompt-title');
    const promptDescEl = document.getElementById('prompt-desc');
    const promptIconBoxEl = document.getElementById('prompt-icon-box');
    const promptIconEl = document.getElementById('prompt-icon');
    const btnGenerateEl = document.getElementById('btn-generate-qr');
    const btnGenerateTextEl = document.getElementById('btn-generate-text');
    const btnCancelQrEl = document.getElementById('btn-cancel-qr');
    const statusBadgeContainerEl = document.getElementById('status-badge-container');

    const qrContainerEl = document.getElementById('user-qrcode');
    const tokenPreviewEl = document.getElementById('token-preview');
    const progressBarEl = document.getElementById('qr-progress-bar');
    const countdownTextEl = document.getElementById('countdown-text');
    const watermarkTimeEl = document.getElementById('watermark-time');
    const btnLogoutEl = document.getElementById('btn-logout');

    /**
     * Arayüzü Kullanıcının Mevcut Durumuna Göre Güncelle (Dışarıda vs Binada)
     */
    function updateStateUI(inside, lastTime = null) {
        isInside = inside;

        if (statusBadgeContainerEl) {
            if (isInside) {
                const timeText = lastTime ? ` (Giriş: ${lastTime})` : '';
                statusBadgeContainerEl.innerHTML = `
                    <span class="badge-status-inside" id="inside-status-badge">
                        <i class="fa-solid fa-building-circle-check"></i> Binadasınız${timeText}
                    </span>
                `;
            } else {
                statusBadgeContainerEl.innerHTML = `
                    <span class="badge-status-outside" id="inside-status-badge">
                        <i class="fa-solid fa-person-walking-arrow-right"></i> Dışarıdasınız
                    </span>
                `;
            }
        }

        if (promptIconBoxEl) {
            promptIconBoxEl.className = `prompt-icon-box ${isInside ? 'prompt-icon-inside' : 'prompt-icon-outside'}`;
        }
        if (promptIconEl) {
            promptIconEl.className = `fa-solid ${isInside ? 'fa-arrow-right-from-bracket' : 'fa-door-open'}`;
        }
        if (promptTitleEl) {
            promptTitleEl.textContent = isInside ? 'Çıkış Yapmak İçin Kod Üretin' : 'Binaya Giriş Yapmak İçin Kod Üretin';
        }
        if (promptDescEl) {
            promptDescEl.textContent = isInside 
                ? 'Mesainizi sonlandırmak veya turnikeden çıkmak için butona basınız.' 
                : 'Turnikeden geçiş yapmak için butona basarak dinamik QR kodunuzu oluşturun.';
        }
        if (btnGenerateEl) {
            btnGenerateEl.className = `btn btn-generate-action ${isInside ? 'btn-exit-style' : 'btn-entry-style'}`;
        }
        if (btnGenerateTextEl) {
            btnGenerateTextEl.textContent = isInside ? 'Çıkış QR Kodu Üret' : 'Giriş QR Kodu Üret';
        }
    }

    /**
     * QR Kod Çizimi
     */
    function renderQRCode(payload) {
        if (tokenPreviewEl) {
            tokenPreviewEl.textContent = payload;
        }
        if (!qrContainerEl) return;

        qrContainerEl.innerHTML = '';
        qrCodeInstance = new QRCode(qrContainerEl, {
            text: payload,
            width: 216,
            height: 216,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.L
        });
    }

    /**
     * Backend'den Güvenli HMAC-SHA256 Token Çek
     */
    async function fetchBackendToken() {
        if (!isQrActive || isFetchingToken) return;
        isFetchingToken = true;

        try {
            const response = await fetch('api/get_my_token.php?action=token&_t=' + Date.now(), {
                method: 'GET',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });

            if (response.status === 401) {
                window.location.href = 'login.php';
                return;
            }

            const data = await response.json();

            if (data.status && data.qr_payload) {
                // Eğer son geçiş ID'si değişmişse, turnikeden geçilmiş demektir!
                if (data.last_pass_id && knownPassId && data.last_pass_id > knownPassId) {
                    knownPassId = data.last_pass_id;
                    handlePassDetected(data.inside, data.last_time);
                    return;
                }

                knownPassId = data.last_pass_id || knownPassId;
                renderQRCode(data.qr_payload);

                const backendRemainingSec = typeof data.kalan_sure === 'number' ? data.kalan_sure : 10;
                remainingTimeMs = Math.max(1, backendRemainingSec) * 1000;
                updateProgressDisplay();
            }
        } catch (error) {
            console.error('[PDKS] Token Alma Hatası:', error);
            remainingTimeMs = 2000;
        } finally {
            isFetchingToken = false;
        }
    }

    /**
     * Turnikeden Geçiş Yapıldığında QR Kodunu Otomatik Kapat ve Onay Ver
     */
    function handlePassDetected(newInsideState, actionTime) {
        console.log("🎉 Turnike geçişi algılandı! Yeni durum:", newInsideState ? "İçeride" : "Dışarıda");
        
        // QR'ı hemen kapat
        closeQrCode();
        updateStateUI(newInsideState, actionTime);

        // Bildirim Sesi
        playSuccessChime();

        // Görsel Onay Kutusu
        Swal.fire({
            icon: 'success',
            title: newInsideState ? 'GİRİŞ ONAYLANDI' : 'ÇIKIŞ ONAYLANDI',
            text: newInsideState 
                ? `Hoş geldiniz! Mesainiz başlatıldı (${actionTime || 'Şimdi'}).` 
                : `İyi günler! Çıkışınız kaydedildi (${actionTime || 'Şimdi'}).`,
            timer: 3000,
            showConfirmButton: false,
            background: '#FFFFFF',
            color: '#0F172A'
        });
    }

    /**
     * QR Açıkken Turnike Geçişini Hızlıca Algılayan Polling Servisi (Her 1.5 sn)
     */
    async function checkPassStatus() {
        if (!isQrActive) return;

        try {
            const response = await fetch('api/get_my_token.php?action=status&_t=' + Date.now(), {
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();

            if (data.status && data.last_pass_id) {
                if (knownPassId && data.last_pass_id > knownPassId) {
                    knownPassId = data.last_pass_id;
                    handlePassDetected(data.inside, data.last_time);
                } else {
                    knownPassId = data.last_pass_id;
                }
            }
        } catch (e) {}
    }

    /**
     * Sayaç ve Progress Bar'ı Güncelle
     */
    function updateProgressDisplay() {
        const percentage = (remainingTimeMs / WINDOW_DURATION_MS) * 100;
        const secondsLeft = (remainingTimeMs / 1000).toFixed(1);

        if (progressBarEl) {
            progressBarEl.style.width = `${Math.max(0, Math.min(100, percentage))}%`;
            if (remainingTimeMs <= 3000) {
                progressBarEl.classList.add('progress-line-warning');
            } else {
                progressBarEl.classList.remove('progress-line-warning');
            }
        }

        if (countdownTextEl) {
            countdownTextEl.textContent = `${secondsLeft} sn`;
        }
    }

    /**
     * Geri Sayım Sayacı (50ms döngü)
     */
    function startTimer() {
        if (timerId) clearInterval(timerId);
        
        const TICK_MS = 50;
        timerId = setInterval(() => {
            if (!isQrActive) return;
            remainingTimeMs -= TICK_MS;

            if (remainingTimeMs <= 0) {
                fetchBackendToken();
            } else {
                updateProgressDisplay();
            }
        }, TICK_MS);
    }

    /**
     * Ekran Görüntüsü Filigran Saati
     */
    function startWatermarkClock() {
        if (watermarkTimerId) clearInterval(watermarkTimerId);

        watermarkTimerId = setInterval(() => {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('tr-TR', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const msStr = String(Math.floor(now.getMilliseconds() / 10)).padStart(2, '0');
            
            if (watermarkTimeEl) {
                watermarkTimeEl.textContent = `CANLI: ${timeStr}.${msStr}`;
            }
        }, 80);
    }

    /**
     * Butona Tıklandığında QR Kodu Aç
     */
    function openQrCode() {
        isQrActive = true;
        promptSectionEl.classList.add('d-none');
        qrDisplaySectionEl.classList.remove('d-none');

        // İlk token'ı çek ve sayaçları başlat
        fetchBackendToken();
        startTimer();

        // Turnike geçişini algılamak için 1.5 sn'lik polling başlat
        if (statusPollTimerId) clearInterval(statusPollTimerId);
        statusPollTimerId = setInterval(checkPassStatus, 1500);
    }

    /**
     * QR Kodunu Kapat ve Talep Ekranına Dön
     */
    function closeQrCode() {
        isQrActive = false;
        qrDisplaySectionEl.classList.add('d-none');
        promptSectionEl.classList.remove('d-none');

        if (timerId) clearInterval(timerId);
        if (statusPollTimerId) clearInterval(statusPollTimerId);
    }

    function playSuccessChime() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
            osc.frequency.setValueAtTime(880, audioCtx.currentTime + 0.1); // A5
            gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.35);
        } catch (e) {}
    }

    /**
     * Uygulamayı Başlat
     */
    function init() {
        // İlk Arayüz Durumunu Yükle
        updateStateUI(isInside, currentUser.lastActionTime);
        startWatermarkClock();

        // Buton Dinleyicileri
        if (btnGenerateEl) {
            btnGenerateEl.addEventListener('click', openQrCode);
        }
        if (btnCancelQrEl) {
            btnCancelQrEl.addEventListener('click', closeQrCode);
        }

        // Çıkış Butonu
        if (btnLogoutEl) {
            btnLogoutEl.addEventListener('click', async (e) => {
                e.preventDefault();
                if (confirm('Personel oturumunu kapatmak istiyor musunuz?')) {
                    try {
                        await fetch('api/auth.php?action=logout');
                    } catch (err) {}
                    window.location.href = 'login.php';
                }
            });
        }

        // Sayfa tekrar aktifleştiğinde durumu güncelle
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                if (isQrActive) {
                    fetchBackendToken();
                } else {
                    checkPassStatus();
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
