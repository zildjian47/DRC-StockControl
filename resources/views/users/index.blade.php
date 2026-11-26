<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Manajemen User"
                       subtitle="Verifikasi & ACC pendaftar akun baru, pengaturan role divisi (PPIC, Staff Material, Staff FG), penonaktifan akun, dan kirim tautan reset password">
            <x-slot name="actions">
                <span class="btn-group">
                    <span class="pill pill-warn">{{ $pendingCount }} MENUNGGU ACC</span>
                    <span class="pill pill-good">{{ $activeCount }} AKTIF</span>
                    <span class="pill pill-neu">{{ $inactiveCount }} NONAKTIF</span>
                </span>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash />

    <div class="panel"
         x-data="userMgmtPage({{ $users->count() }}, {{ (int) Auth::id() }})"
         x-init="recalc()"
         @table-refreshed.window="recalc()">

        <div class="panel-head">
            <div class="panel-title">
                <span>Daftar Akun Pengguna</span>
                <span class="count-chip"><span x-text="visibleCount"></span><span>AKUN</span></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text" x-model="q" @input="recalc()"
                       placeholder="Cari Nama, Username, Email..."
                       class="h-8 text-xs px-2.5 w-52">

                <select x-model="fStatus" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Status</option>
                    <option value="PENDING">MENUNGGU ACC ({{ $pendingCount }})</option>
                    <option value="ACTIVE">AKTIF ({{ $activeCount }})</option>
                    <option value="INACTIVE">NONAKTIF ({{ $inactiveCount }})</option>
                </select>

                <select x-model="fRole" @change="recalc()" class="h-8 text-xs py-0 pl-2 pr-7">
                    <option value="">Semua Role</option>
                    <option value="PPIC">PPIC</option>
                    <option value="STAFF_MATERIAL">Staff Material</option>
                    <option value="STAFF_FG">Staff FG</option>
                </select>

                <button type="button"
                        x-show="q || fStatus || fRole" x-cloak
                        @click="resetFilter()" class="btn btn-sm">
                    Reset
                </button>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-[4%] text-center">No</th>
                        <th class="w-[20%]">Nama &amp; Username</th>
                        <th class="w-[20%]">Email</th>
                        <th class="w-[14%] text-center">Role / Divisi</th>
                        <th class="w-[12%] text-center">Status</th>
                        <th class="w-[16%]">Info Pendaftaran &amp; ACC</th>
                        <th class="w-[14%] text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $idx =>$usr)
                        @php
                            $isSelf       = (int)$usr->id === (int) Auth::id();
                            $status       =$usr->status ?? 'ACTIVE';
                            $reqRoleLabel = match ($usr->requested_role) {
                                'STAFF_MATERIAL' => 'Staff Material',
                                'STAFF_FG'       => 'Staff FG',
                                'PPIC'           => 'PPIC',
                                default          => $usr->role_label,
                            };

                            $modalPayload = [
                                'id'              => $usr->id,
                                'name'            => $usr->name,
                                'username'        => $usr->username ?? '',
                                'email'           => $usr->email,
                                'role'            => $usr->role,
                                'role_label'      => $usr->role_label,
                                'requested_role'  => $usr->requested_role ?: $usr->role,
                                'req_role_label'  => $reqRoleLabel,
                                'status'          => $status,
                                'is_self'         => $isSelf,
                                'created_at_fmt'  => $usr->created_at ? $usr->created_at->format('d M Y, H:i') : '-',
                                'approved_by'     => $usr->approvedBy->name ?? null,
                                'approved_at_fmt' => $usr->approved_at ? $usr->approved_at->format('d M Y, H:i') : null,
                            ];
                        @endphp
                        <tr data-row
                            data-search="{{ strtolower($usr->name . ' ' . ($usr->username ?? '') . ' ' . $usr->email . ' ' .$usr->role_label) }}"
                            data-status="{{ $status }}"
                            data-role="{{ $usr->role }}"
                            x-show="matchRow($el)">
                            <td class="text-center num font-semibold text-slate-500" data-col-no>{{ $idx + 1 }}</td>
                            <td>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-semibold text-slate-900">{{ $usr->name }}</span>
                                    @if($isSelf)
                                        <span class="pill pill-info">ANDA</span>
                                    @endif
                                </div>
                                <div class="num text-xs text-slate-500 mt-0.5">
                                    {{ $usr->username ? '@' . $usr->username : 'Belum ada username' }}
                                </div>
                            </td>
                            <td>
                                <span class="text-xs font-medium text-slate-800 break-all">{{ $usr->email }}</span>
                            </td>
                            <td class="text-center">
                                @if($status === 'PENDING')
                                    <div class="text-[10.5px] font-semibold uppercase text-slate-500 mb-0.5">Pengajuan:</div>
                                    <span class="pill pill-warn">{{ strtoupper($reqRoleLabel) }}</span>
                                @elseif($usr->role === 'PPIC')
                                    <span class="pill pill-info">PPIC</span>
                                @elseif($usr->role === 'STAFF_MATERIAL')
                                    <span class="pill pill-warn">STAFF MATERIAL</span>
                                @else
                                    <span class="pill pill-good">STAFF FG</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($status === 'PENDING')
                                    <span class="pill pill-warn">MENUNGGU ACC</span>
                                @elseif($status === 'ACTIVE')
                                    <span class="pill pill-good">AKTIF</span>
                                @else
                                    <span class="pill pill-crit">NONAKTIF</span>
                                @endif
                            </td>
                            <td class="text-xs leading-snug">
                                <div class="num text-slate-600">Daftar: {{ $usr->created_at ? $usr->created_at->format('d M Y, H:i') : '-' }}</div>
                                @if($usr->approvedBy)
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        ACC: <b class="text-slate-700">{{ $usr->approvedBy->name }}</b>
                                        @if($usr->approved_at)
                                            <span class="num">({{ $usr->approved_at->format('d M Y') }})</span>
                                        @endif
                                    </div>
                                @elseif($status === 'PENDING')
                                    <div class="text-[11px] font-semibold text-amber-700 mt-0.5">Perlu verifikasi PPIC</div>
                                @else
                                    <div class="text-[11px] text-slate-400 mt-0.5">Akun sistem awal</div>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="inline-flex items-center justify-center gap-1.5 flex-wrap">
                                    @if($status === 'PENDING')
                                        <button type="button"
                                                class="btn btn-sm btn-primary whitespace-nowrap"
                                                @click="openApprove({{ Js::from($modalPayload) }})">
                                            <x-icon name="check" />
                                            <span>ACC</span>
                                        </button>

                                        <form action="{{ route('users.destroy', $usr->id) }}" method="POST" class="inline"
                                              @submit.prevent="$dispatch('open-confirm', { form:$el, title: 'Tolak Pendaftaran Akun', message: {{ Js::from('Tolak dan hapus pendaftaran atas nama ' . $usr->name . ' (' .$usr->email . ')?') }} })">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="act-btn danger" title="Tolak & Hapus Pendaftar">
                                                <x-icon name="trash" />
                                            </button>
                                        </form>
                                    @else
                                        <button type="button" class="act-btn" title="Edit Data, Role, atau Status Akun"
                                                @click="openEdit({{ Js::from($modalPayload) }})">
                                            <x-icon name="edit" />
                                        </button>

                                        @if($status === 'ACTIVE')
                                            <form action="{{ route('users.send_reset_link', $usr->id) }}" method="POST" class="inline"
                                                  @submit.prevent="$dispatch('open-confirm', { form:$el, title: 'Kirim Tautan Reset Password', message: {{ Js::from('Kirim email tautan reset password ke ' . $usr->email . ' atas nama ' .$usr->name . '?') }} })">
                                                @csrf
                                                <button type="submit" class="btn btn-sm whitespace-nowrap" title="Kirim tautan reset password ke Gmail pengguna">
                                                    <span>Reset PW</span>
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty">Belum ada data akun pengguna.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- POP-UP MODAL ACC PENDAFTAR BARU (Tanpa Kolom Password) --}}
        <div x-show="approveOpen" x-cloak class="modal-backdrop" @click.self="approveOpen = false" @keydown.escape.window="approveOpen = false">
            <div class="panel dlg w-full max-w-3xl" role="dialog" aria-label="Verifikasi & ACC Pendaftar Baru">
                <div class="panel-head">
                    <h3 class="panel-title">Verifikasi &amp; ACC Pendaftar Baru</h3>
                    <button type="button" class="act-btn" @click="approveOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form :action="'{{ url('users') }}/' + approveData.id + '/approve'" method="POST" novalidate
                      @submit.prevent="submitApprove($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    @method('PATCH')
                    <div class="dlg-split">
                        <aside class="dlg-side hide-sm">
                            <div class="dlg-k">Detail Pengajuan</div>
                            <div class="sumc">
                                <span class="pill pill-warn w-fit">MENUNGGU ACC</span>
                                <div class="dlg-name mt-2" x-text="approveData.name"></div>
                                <div class="num text-xs text-slate-600 mt-0.5" x-text="'@' + approveData.username"></div>
                                <div class="text-xs text-slate-500 break-all mt-1" x-text="approveData.email"></div>
                            </div>
                            <div class="dlg-list">
                                <div><span>Waktu Daftar</span><b class="num text-[11px]" x-text="approveData.created_at_fmt"></b></div>
                                <div><span>Divisi Diajukan</span><b class="text-amber-700" x-text="approveData.req_role_label"></b></div>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <div x-show="approveError" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="approveError"></span>
                            </div>

                            <div>
                                <label class="lbl" for="acc_name">Nama Lengkap</label>
                                <input id="acc_name" type="text" name="name" x-model="approveData.name" required class="w-full h-9 text-xs">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="lbl" for="acc_username">Username Login</label>
                                    <input id="acc_username" type="text" name="username" x-model="approveData.username" required class="w-full h-9 text-xs lowercase num">
                                </div>
                                <div>
                                    <label class="lbl" for="acc_email">Email (Gmail Aktif)</label>
                                    <input id="acc_email" type="email" name="email" x-model="approveData.email" required class="w-full h-9 text-xs">
                                </div>
                            </div>

                            <div>
                                <span class="lbl">Tetapkan Role Final</span>
                                <div class="opt-chips">
                                    <label>
                                        <input type="radio" name="role" value="STAFF_MATERIAL" x-model="approveData.role" required>
                                        <span>Staff Material</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="role" value="STAFF_FG" x-model="approveData.role" required>
                                        <span>Staff FG</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="role" value="PPIC" x-model="approveData.role" required>
                                        <span>PPIC</span>
                                    </label>
                                </div>
                            </div>

                            <div class="alert alert-info text-xs leading-relaxed">
                                <x-icon name="info" />
                                <div>Password dibuat sendiri oleh pendaftar dan terenkripsi privat. Setelah klik <b>ACC &amp; Aktifkan</b>, pengguna dapat langsung masuk menggunakan Username/Email dan passwordnya.</div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="approveOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">ACC &amp; Aktifkan</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- POP-UP MODAL EDIT DATA, ROLE, & STATUS AKUN (Tanpa Kolom Password) --}}
        <div x-show="editOpen" x-cloak class="modal-backdrop" @click.self="editOpen = false" @keydown.escape.window="editOpen = false">
            <div class="panel dlg w-full max-w-3xl" role="dialog" aria-label="Edit Data & Hak Akses Akun">
                <div class="panel-head">
                    <h3 class="panel-title">Edit Data &amp; Hak Akses Akun</h3>
                    <button type="button" class="act-btn" @click="editOpen = false" aria-label="Tutup">
                        <x-icon name="x" />
                    </button>
                </div>

                <form :action="'{{ url('users') }}/' + editData.id" method="POST" novalidate
                      @submit.prevent="submitEdit($el)"
                      class="flex flex-col min-h-0 flex-1">
                    @csrf
                    @method('PUT')
                    <div class="dlg-split">
                        <aside class="dlg-side hide-sm">
                            <div class="dlg-k">Ringkasan Akun</div>
                            <div class="sumc">
                                <span class="pill w-fit" :class="editData.status === 'ACTIVE' ? 'pill-good' : 'pill-crit'"
                                      x-text="editData.status === 'ACTIVE' ? 'AKTIF' : 'NONAKTIF'"></span>
                                <div class="dlg-name mt-2" x-text="editData.name"></div>
                                <div class="num text-xs text-slate-600 mt-0.5" x-text="editData.username ? '@' + editData.username : '-'"></div>
                                <div class="text-xs text-slate-500 break-all mt-1" x-text="editData.email"></div>
                            </div>
                            <div class="dlg-list">
                                <div><span>Terdaftar</span><b class="num text-[11px]" x-text="editData.created_at_fmt"></b></div>
                                <div x-show="editData.approved_by"><span>Di-ACC Oleh</span><b class="text-[11px]" x-text="editData.approved_by"></b></div>
                            </div>
                        </aside>

                        <div class="dlg-main">
                            <div x-show="editError" x-cloak class="alert alert-crit text-xs" role="alert">
                                <x-icon name="alert" />
                                <span x-text="editError"></span>
                            </div>

                            <div x-show="editData.is_self" x-cloak class="alert alert-info text-xs">
                                <x-icon name="info" />
                                <span>Ini adalah akun PPIC yang sedang Anda gunakan. Role dan status aktif akun sendiri dikunci untuk mencegah terkunci dari sistem.</span>
                            </div>

                            <div>
                                <label class="lbl" for="ed_name">Nama Lengkap</label>
                                <input id="ed_name" type="text" name="name" x-model="editData.name" required class="w-full h-9 text-xs">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="lbl" for="ed_username">Username Login</label>
                                    <input id="ed_username" type="text" name="username" x-model="editData.username" required class="w-full h-9 text-xs lowercase num">
                                </div>
                                <div>
                                    <label class="lbl" for="ed_email">Email (Gmail Aktif)</label>
                                    <input id="ed_email" type="email" name="email" x-model="editData.email" required class="w-full h-9 text-xs">
                                </div>
                            </div>

                            <div>
                                <span class="lbl">Role Divisi</span>
                                <template x-if="editData.is_self">
                                    <input type="hidden" name="role" value="PPIC">
                                </template>
                                <div class="opt-chips">
                                    <label>
                                        <input type="radio" name="role" value="STAFF_MATERIAL" x-model="editData.role" :disabled="editData.is_self" required>
                                        <span>Staff Material</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="role" value="STAFF_FG" x-model="editData.role" :disabled="editData.is_self" required>
                                        <span>Staff FG</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="role" value="PPIC" x-model="editData.role" :disabled="editData.is_self" required>
                                        <span>PPIC</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <span class="lbl">Status Akun</span>
                                <template x-if="editData.is_self">
                                    <input type="hidden" name="status" value="ACTIVE">
                                </template>
                                <div class="opt-chips">
                                    <label>
                                        <input type="radio" name="status" value="ACTIVE" x-model="editData.status" :disabled="editData.is_self" required>
                                        <span>AKTIF</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="status" value="INACTIVE" x-model="editData.status" :disabled="editData.is_self" required>
                                        <span>NONAKTIF (Resign / Blokir)</span>
                                    </label>
                                </div>
                            </div>

                            <div class="alert alert-info text-xs leading-relaxed">
                                <x-icon name="info" />
                                <div>Menonaktifkan akun akan langsung mengeluarkan sesi login perangkat karyawan tersebut tanpa menghapus nama petugas dari histori transaksi lama.</div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-foot">
                        <button type="button" class="btn" @click="editOpen = false">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function userMgmtPage(initialCount, selfId) {
            return {
                q: '',
                fStatus: '',
                fRole: '',
                visibleCount: initialCount,
                selfId: selfId,

                approveOpen: false,
                approveError: '',
                approveData: {
                    id: null,
                    name: '',
                    username: '',
                    email: '',
                    role: 'STAFF_MATERIAL',
                    req_role_label: '',
                    created_at_fmt: ''
                },

                editOpen: false,
                editError: '',
                editData: {
                    id: null,
                    name: '',
                    username: '',
                    email: '',
                    role: 'STAFF_MATERIAL',
                    status: 'ACTIVE',
                    is_self: false,
                    created_at_fmt: '',
                    approved_by: null
                },

                openApprove(p) {
                    this.approveData = {
                        id: p.id,
                        name: p.name || '',
                        username: p.username || '',
                        email: p.email || '',
                        role: p.requested_role || 'STAFF_MATERIAL',
                        req_role_label: p.req_role_label || 'Staff Material',
                        created_at_fmt: p.created_at_fmt || '-'
                    };
                    this.approveError = '';
                    this.approveOpen = true;
                },
                openEdit(p) {
                    this.editData = {
                        id: p.id,
                        name: p.name || '',
                        username: p.username || '',
                        email: p.email || '',
                        role: p.role || 'STAFF_MATERIAL',
                        status: p.status || 'ACTIVE',
                        is_self: Boolean(p.is_self),
                        created_at_fmt: p.created_at_fmt || '-',
                        approved_by: p.approved_by || null
                    };
                    this.editError = '';
                    this.editOpen = true;
                },
                validateCommon(d) {
                    if (!String(d.name || '').trim()) return 'Nama lengkap wajib diisi.';
                    const uname = String(d.username || '').trim().toLowerCase();
                    if (uname.length < 3 || !/^[a-z0-9._-]+$/.test(uname)) {
                        return 'Username minimal 3 karakter dan hanya boleh huruf kecil, angka, titik (.), garis bawah (_), atau tanda hubung (-).';
                    }
                    const mail = String(d.email || '').trim();
                    if (!mail || !mail.includes('@')) return 'Format email tidak valid.';
                    if (!['PPIC', 'STAFF_MATERIAL', 'STAFF_FG'].includes(d.role)) return 'Pilih role divisi yang valid.';
                    return '';
                },
                submitApprove(formEl) {
                    this.approveError = this.validateCommon(this.approveData);
                    if (this.approveError) return;
                    formEl.submit();
                },
                submitEdit(formEl) {
                    this.editError = this.validateCommon(this.editData);
                    if (this.editError) return;
                    if (!['ACTIVE', 'INACTIVE'].includes(this.editData.status)) {
                        this.editError = 'Pilih status akun yang valid.';
                        return;
                    }
                    formEl.submit();
                },
                resetFilter() {
                    this.q = '';
                    this.fStatus = '';
                    this.fRole = '';
                    this.recalc();
                },
                matchRow(el) {
                    const s = el.dataset.search || '';
                    const st = el.dataset.status || '';
                    const r = el.dataset.role || '';

                    if (this.q && !s.includes(this.q.toLowerCase())) return false;
                    if (this.fStatus && st !== this.fStatus) return false;
                    if (this.fRole && r !== this.fRole) return false;
                    return true;
                },
                recalc() {
                    this.$nextTick(() => {
                        let c = 0;
                        this.$el.querySelectorAll('tbody tr[data-row]').forEach(tr => {
                            if (this.matchRow(tr)) {
                                c++;
                                const noCell = tr.querySelector('[data-col-no]');
                                if (noCell) noCell.textContent = c;
                            }
                        });
                        this.visibleCount = c;
                    });
                }
            };
        }
    </script>
</x-app-layout>