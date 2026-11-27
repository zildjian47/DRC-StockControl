<x-scanner-layout :backUrl="route('scanner.portal')">
    <x-slot name="header">Cek Info Stok</x-slot>

    <div x-data="itemInfoScanner({{ Js::from(route('item_cards.lookup')) }})"
         x-init="initScanner()"
         class="space-y-3.5">

        {{-- KARTU KAMERA SCAN QR --}}
        <section class="panel">
            <div class="panel-head">
                <div class="panel-title">Scan QR Rak atau Label Part</div>
                <span class="pill pill-info">INFO ONLY</span>
            </div>

            <div class="panel-body space-y-3">
                <div class="camera-box">
                    <div id="qr-reader"></div>
                    <div class="scan-scope-guide" x-show="cameraActive" x-cloak></div>

                    <div x-show="!cameraActive" class="absolute inset-0 flex flex-col items-center justify-center p-5 text-center bg-[#0B1524] text-slate-200 space-y-3">
                        <div class="w-11 h-11 rounded bg-white/10 grid place-items-center text-white">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7" rx="1"/>
                                <rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="3" y="14" width="7" height="7" rx="1"/>
                                <path d="M14 14h3v3h-3zM20 14h1v3M14 20h7"/>
                            </svg>
                        </div>
                        <div class="text-xs leading-relaxed text-slate-300">
                            Arahkan kamera ke stiker <b>QR Info Rak</b> maupun stiker <b>QR Part No / Part Name</b> lama.
                        </div>
                        <button type="button" @click="startCamera()" class="btn btn-primary btn-sm">
                            Aktifkan Kamera
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11.5px] text-slate-500"
                          x-text="loading ? 'Mencari data item...' : (cameraActive ? 'Kamera aktif — bidik kode QR ke dalam kotak' : 'Kamera nonaktif')"></span>
                    <button type="button" x-show="cameraActive" x-cloak @click="stopCamera()" class="btn btn-sm">
                        Matikan Kamera
                    </button>
                </div>
            </div>
        </section>

        {{-- INPUT MANUAL KODE / PART NO / PART NAME --}}
        <section class="panel">
            <div class="panel-head">
                <div class="panel-title">Cari Manual</div>
            </div>
            <div class="panel-body space-y-3">
                <form @submit.prevent="lookupCode(manualCode)" class="space-y-2.5">
                    <div>
                        <label for="manual_code" class="lbl">Part No, Part Name, atau URL QR</label>
                        <input id="manual_code"
                               type="text"
                               x-model="manualCode"
                               placeholder="Contoh: FG-STAY-01 atau COIL SPHC 2.3 X 120"
                               :disabled="loading"
                               class="w-full h-10 text-sm">
                    </div>

                    <button type="submit" :disabled="loading || !manualCode.trim()" class="btn btn-primary btn-lg w-full justify-center">
                        <span x-show="!loading">Lihat Kartu Info Stok</span>
                        <span x-show="loading" x-cloak>Membuka Info Item...</span>
                    </button>
                </form>

                <div class="alert alert-info text-xs leading-relaxed">
                    <x-icon name="info" />
                    <div>Mode ini hanya untuk melihat saldo stok, WIP aktif, BOM, dan riwayat mutasi. Tidak mengubah stok gudang.</div>
                </div>
            </div>
        </section>
    </div>

    <script>
        function itemInfoScanner(lookupUrl) {
            return {
                lookupUrl,
                cameraActive: false,
                loading: false,
                manualCode: '',
                lastCode: '',
                lastScanAt: 0,

                initScanner() {
                    if (window.DRCScanner && window.DRCScanner.isPermitted()) {
                        this.startCamera();
                    }
                },

                async startCamera() {
                    if (!window.DRCScanner) return;
                    const ok = await window.DRCScanner.start(
                        'qr-reader',
                        (decodedText) => this.onDecoded(decodedText),
                        (errMsg) => {
                            this.cameraActive = false;
                            if (window.DRCToast) window.DRCToast.show(errMsg, 'crit');
                        }
                    );
                    this.cameraActive = ok;
                },

                async stopCamera() {
                    if (window.DRCScanner) {
                        await window.DRCScanner.stop();
                    }
                    this.cameraActive = false;
                },

                onDecoded(text) {
                    const code = String(text || '').trim();
                    if (!code || this.loading) return;

                    const now = Date.now();
                    if (code === this.lastCode && (now - this.lastScanAt) < 2500) {
                        return;
                    }
                    this.lastCode = code;
                    this.lastScanAt = now;
                    this.manualCode = code;
                    this.lookupCode(code);
                },

                async lookupCode(code) {
                    const trimmed = String(code || '').trim();
                    if (!trimmed || this.loading) return;

                    this.loading = true;
                    try {
                        const url = `${this.lookupUrl}?code=${encodeURIComponent(trimmed)}`;
                        const res = await fetch(url, {
                            method: 'GET',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (res.redirected || res.status === 401 || res.status === 419) {
                            if (window.DRCToast) {
                                window.DRCToast.show('Sesi habis, silakan muat ulang halaman untuk masuk kembali.', 'crit');
                            }
                            this.loading = false;
                            return;
                        }

                        const contentType = res.headers.get('content-type') || '';
                        if (!contentType.includes('application/json')) {
                            if (window.DRCToast) {
                                window.DRCToast.show('Sesi habis atau respons server tidak valid. Silakan muat ulang halaman.', 'crit');
                            }
                            this.loading = false;
                            return;
                        }

                        const data = await res.json();
                        if (!res.ok || !data.ok || !data.url) {
                            const msg = (data && data.message) ? data.message : 'Item tidak ditemukan.';
                            if (window.DRCToast) window.DRCToast.show(msg, 'crit');
                            this.loading = false;
                            return;
                        }

                        await this.stopCamera();
                        if (window.DRCToast) {
                            window.DRCToast.show(`Ditemukan: ${data.item.part_name}`, 'good', 1500);
                        }
                        window.location.href = data.url;
                    } catch (e) {
                        if (window.DRCToast) {
                            window.DRCToast.show('Gagal menghubungi server. Periksa koneksi jaringan.', 'crit');
                        }
                        this.loading = false;
                    }
                }
            };
        }
    </script>
</x-scanner-layout>