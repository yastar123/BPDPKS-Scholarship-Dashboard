import { useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { QueryClient, QueryClientProvider, useQueryClient } from '@tanstack/react-query';
import { Link, Route, Switch, useLocation } from 'wouter';
import {
  Activity, AlertCircle, ArrowDownRight, ArrowRight, ArrowUpRight, BadgeCheck,
  BookOpen, Building2, Check, ChevronDown, CircleHelp, ClipboardList, GraduationCap,
  LayoutDashboard, MapPin, Menu, Pencil, Plus, Search, SlidersHorizontal, Sparkles, Users, X,
} from 'lucide-react';
import {
  getGetScholarshipApplicantsQueryKey, getGetScholarshipDashboardQueryKey,
  useCreateScholarshipApplicant, useGetScholarshipApplicants, useGetScholarshipDashboard,
  useSaveScholarshipCapacity,
  useHealthCheck, useUpdateScholarshipApplicant,
} from '@workspace/api-client-react';
import type { ApplicantInput, ApplicantUpdate, CapacityInput, CampusCapacity, ScholarshipApplicant } from '@workspace/api-client-react';

const qc = new QueryClient();
const money = (n?: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n ?? 0);
const number = (n?: number) => new Intl.NumberFormat('id-ID').format(n ?? 0);
const percent = (n?: number) => `${(n ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`;
const pathLabels: Record<string, string> = { pekebun: 'Pekebun', keluarga_pekebun: 'Keluarga pekebun', karyawan_sawit: 'Karyawan sawit', keluarga_karyawan: 'Keluarga karyawan', pengurus_asosiasi: 'Pengurus asosiasi', asn: 'ASN', penyuluh: 'Penyuluh' };
const statusLabels: Record<string, string> = { mendaftar: 'Mendaftar', lolos_administrasi: 'Lolos administrasi', lolos_tes: 'Lolos tes', diterima: 'Diterima', mengundurkan_diri: 'Mengundurkan diri', aktif: 'Aktif', cuti: 'Cuti', putus: 'Putus studi', lulus: 'Lulus' };
const display = (v?: string | null) => v ? (statusLabels[v] ?? v.replaceAll('_', ' ')) : '—';
const cls = (...values: Array<string | false | undefined>) => values.filter(Boolean).join(' ');

function App() {
  return <QueryClientProvider client={qc}><Shell /></QueryClientProvider>;
}

function Shell() {
  const [location] = useLocation();
  const [mobileOpen, setMobileOpen] = useState(false);
  const { data: health } = useHealthCheck();
  const nav = [{ href: '/', label: 'Ringkasan', icon: LayoutDashboard }, { href: '/applicants', label: 'Pendaftar & penerima', icon: Users }];
  return <div className="app-frame">
    <aside className={cls('sidebar', mobileOpen && 'sidebar-open')}>
      <div className="brand"><div className="brand-mark"><span>BP</span></div><div><b>BPDPKS</b><small>Ruang keputusan beasiswa</small></div><button className="icon-btn sidebar-close" aria-label="Tutup navigasi" onClick={() => setMobileOpen(false)}><X size={17}/></button></div>
      <div className="side-caption">PENGELOLAAN PROGRAM</div>
      <nav className="side-nav">{nav.map(item => <Link key={item.href} href={item.href} data-testid={`link-${item.label}`} onClick={() => setMobileOpen(false)} className={cls('nav-link', location === item.href && 'active')}><item.icon size={18}/><span>{item.label}</span>{item.href === location && <i/>}</Link>)}</nav>
      <div className="side-bottom"><div className="side-note"><div className="note-icon"><CircleHelp size={17}/></div><b>Indikator yang dapat ditelusuri</b><p>Setiap penanda keputusan berasal dari data dan aturan program.</p></div><div className="health-line"><span className={cls('health-dot', health?.status === 'ok' && 'online')}/><span>{health?.status === 'ok' ? 'Layanan tersambung' : 'Memeriksa layanan'}</span><span className="mono">API</span></div><div className="side-foot">BPDPKS <span>•</span> Sistem pendukung program</div></div>
    </aside>
    {mobileOpen && <button className="scrim" aria-label="Tutup navigasi" onClick={() => setMobileOpen(false)}/>}
    <main className="main-shell"><header className="topbar"><button className="icon-btn menu-trigger" aria-label="Buka navigasi" onClick={() => setMobileOpen(true)}><Menu size={19}/></button><div className="crumb"><span>Program beasiswa</span><span className="crumb-slash">/</span><b>{location === '/applicants' ? 'Pendaftar & penerima' : 'Ringkasan'}</b></div><div className="top-right"><span className="demo-tag">LINGKUNGAN DEMO</span><span className="top-cycle">Periode berjalan</span><div className="profile-mark">PK</div></div></header>
      <Switch><Route path="/" component={Dashboard}/><Route path="/applicants" component={Applicants}/><Route><div className="page-wrap"><div className="error-state"><AlertCircle/><h2>Halaman tidak ditemukan</h2><Link href="/" className="button primary">Kembali ke ringkasan</Link></div></div></Route></Switch>
    </main>
  </div>;
}

function PageHeading({ eyebrow, title, subtitle, actions }: { eyebrow: string; title: string; subtitle: string; actions?: ReactNode }) {
  return <div className="page-heading"><div><div className="eyebrow">{eyebrow}</div><h1>{title}</h1><p>{subtitle}</p></div>{actions && <div className="heading-actions">{actions}</div>}</div>;
}
function LoadError({ onRetry }: { onRetry: () => void }) { return <div className="error-state"><AlertCircle size={25}/><h2>Data belum dapat dimuat</h2><p>Periksa koneksi layanan, lalu coba lagi.</p><button onClick={onRetry} className="button secondary">Coba lagi</button></div>; }
function Skeleton({ count = 4 }: { count?: number }) { return <div className="skeleton-grid">{Array.from({ length: count }, (_, i) => <div className="skeleton-card" key={i}><i/><i/><i/></div>)}</div>; }

function Dashboard() {
  const [cohort, setCohort] = useState<number | undefined>();
  const [capacityDraft, setCapacityDraft] = useState<CapacityInput | null>(null);
  const query = useGetScholarshipDashboard(cohort ? { cohort } : undefined);
  const saveCapacity = useSaveScholarshipCapacity();
  const queryClient = useQueryClient();
  const data = query.data;
  const flags = data?.decisionFlags;
  const newCapacity = () => setCapacityDraft({ campus: '', campusProvince: '', program: '', degree: 'D4/S1', cohort: data?.cohort ?? new Date().getFullYear(), quota: 0, budgetCeiling: 0 });
  const editCapacity = (row: CampusCapacity) => setCapacityDraft({ campus: row.campus, campusProvince: (row as CampusCapacity & { campusProvince?: string | null }).campusProvince ?? '', program: row.program, degree: row.degree, cohort: row.cohort, quota: row.quota, budgetCeiling: row.budgetCeiling });
  const submitCapacity = (event: FormEvent) => {
    event.preventDefault();
    if (!capacityDraft) return;
    saveCapacity.mutate({ data: { ...capacityDraft, campusProvince: capacityDraft.campusProvince?.trim() || null } }, {
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: getGetScholarshipDashboardQueryKey() });
        setCapacityDraft(null);
      },
    });
  };
  return <div className="page-wrap">
    <PageHeading eyebrow="PUSAT KENDALI PROGRAM" title="Ringkasan beasiswa" subtitle="Indikator kelayakan, kapasitas, kemajuan studi, dan realisasi dana dalam satu tampilan." actions={<label className="cohort-select"><span>Angkatan</span><select value={cohort ?? ''} onChange={e => setCohort(e.target.value ? Number(e.target.value) : undefined)} data-testid="select-cohort"><option value="">Semua angkatan</option>{(data?.cohortTrend ?? []).map(c => <option key={c.cohort} value={c.cohort}>{c.cohort}</option>)}</select><ChevronDown size={15}/></label>}/>
    {query.isLoading ? <Skeleton count={4}/> : query.isError ? <LoadError onRetry={() => query.refetch()}/> : data ? <>
      <div className="overview-band">
        <div className="overview-intro"><div className="intro-symbol"><Activity size={19}/></div><div><div className="eyebrow light">POTRET PROGRAM {data.cohort}</div><h2>Keputusan yang lebih terarah.</h2><p>Ringkasan ini membaca kondisi program dari data penerima dan kapasitas aktual.</p></div></div>
        <div className="overview-stat"><span>Pendaftar tercatat</span><strong>{number(data.totalApplicants)}</strong><small>seluruh jalur seleksi</small></div>
        <div className="overview-stat"><span>Penerima beasiswa</span><strong>{number(data.recipients)}</strong><small>dari kuota tersedia</small></div>
        <div className="overview-stat quota-stat"><div className="stat-row"><span>Pengisian kuota</span><b>{percent(data.quotaFillRate)}</b></div><div className="meter light-meter"><i style={{ width: `${Math.min(100, data.quotaFillRate)}%` }}/></div><small>{number(data.occupiedQuota)} terisi <span>·</span> {number(data.totalQuota)} kuota</small></div>
      </div>
      <div className="section-head"><div><div className="eyebrow">PANTAUAN UTAMA</div><h2>Program dalam angka</h2></div><div className="updated-label"><span className="health-dot online"/> Data langsung dari layanan</div></div>
      <div className="metrics-grid">
        <Metric icon={GraduationCap} label="Lulus" value={number(data.graduates)} detail={`${percent(data.graduationRate)} dari penerima`} tone="teal" trend="up"/>
        <Metric icon={ArrowDownRight} label="Putus studi" value={number(data.dropouts)} detail={`${percent(data.dropoutRate)} dari penerima`} tone="coral" trend="down"/>
        <Metric icon={Building2} label="Pagu anggaran" value={money(data.budgetCeiling)} detail="Batas dana program" tone="sand"/>
        <Metric icon={Activity} label="Realisasi anggaran" value={money(data.budgetRealized)} detail={`${percent(data.budgetRealizationRate)} dari pagu`} tone="blue"/>
      </div>
      <section className="decision-section">
        <div className="section-head decision-head"><div><div className="eyebrow">PENANDA KEPUTUSAN</div><h2>Perlu perhatian pengelola</h2><p>Penanda menjelaskan kondisi yang perlu ditinjau, bukan skor kelayakan gabungan.</p></div><div className="decision-badge"><Sparkles size={15}/> Berbasis aturan program</div></div>
        <div className="flags-grid">
          <Flag color="amber" count={flags?.ageOverLimit} title="Usia melewati batas" expl="Usia lebih dari 23 tahun saat pendaftaran."/>
          <Flag color="coral" count={flags?.quotaAtRisk} title="Kapasitas berisiko" expl="Kuota kampus terisi setidaknya 90% dari daya tampung."/>
          <Flag color="blue" count={flags?.academicRisk} title="Risiko akademik" expl="IPK di bawah 2,75 saat berstatus aktif atau cuti."/>
          <Flag color="green" count={flags?.certificatePending} title="Sertifikat tertunda" expl="Penerbitan sertifikat perlu ditindaklanjuti."/>
        </div>
      </section>
      <div className="analysis-grid">
        <section className="panel distribution-panel"><div className="panel-title"><div><div className="eyebrow">ASAL PENERIMA</div><h3>Sebaran provinsi</h3></div><MapPin size={18}/></div><div className="bar-list">{data.byProvince.length ? data.byProvince.slice(0, 7).map((x, i) => <BarRow key={x.label} label={x.label} count={x.count} max={Math.max(...data.byProvince.map(r => r.count), 1)} idx={i}/>) : <InlineEmpty label="Belum ada data provinsi"/>}</div><div className="panel-foot">Jumlah penerima per provinsi</div></section>
        <section className="panel distribution-panel"><div className="panel-title"><div><div className="eyebrow">JALUR SELEKSI</div><h3>Komposisi jalur</h3></div><ClipboardList size={18}/></div><div className="path-list">{data.byPath.length ? data.byPath.slice(0, 6).map((x,i) => <div className="path-row" key={x.label}><span className={`path-index pi-${i+1}`}>{String(i+1).padStart(2,'0')}</span><span className="path-name">{pathLabels[x.label] ?? display(x.label)}</span><strong>{number(x.count)}</strong></div>) : <InlineEmpty label="Belum ada data jalur"/>}</div><div className="panel-foot">Pendaftar menurut jalur seleksi</div></section>
      </div>
      <section className="panel capacity-panel"><div className="capacity-panel-heading"><div className="panel-title"><div><div className="eyebrow">DAYA TAMPUNG</div><h3>Kapasitas kampus & program studi</h3></div><Building2 size={18}/></div><button className="button secondary compact-button" onClick={newCapacity} data-testid="button-add-capacity"><Plus size={14}/> Tambah kapasitas</button></div>{data.campusCapacity.length ? <div className="table-wrap"><table><thead><tr><th>Kampus</th><th>Program studi</th><th>Jenjang</th><th>Angkatan</th><th>Kuota</th><th>Terisi</th><th>Ketersediaan</th><th>Anggaran</th><th></th></tr></thead><tbody>{data.campusCapacity.map((row,i) => { const pct = row.quota ? row.occupied / row.quota * 100 : 0; return <tr key={`${row.campus}-${row.program}-${row.degree}-${row.cohort}-${i}`}><td className="campus-cell">{row.campus}</td><td>{row.program}</td><td><span className="degree-tag">{row.degree}</span></td><td>{row.cohort}</td><td>{number(row.quota)}</td><td>{number(row.occupied)}</td><td><div className="capacity-cell"><div className="capacity-meter"><i className={pct >= 90 ? 'near-full' : ''} style={{ width: `${Math.min(100,pct)}%` }}/></div><small>{percent(pct)}</small></div></td><td>{money(row.budgetCeiling)}</td><td><button className="row-action" onClick={()=>editCapacity(row)} aria-label={`Ubah kapasitas ${row.campus}, ${row.program}, angkatan ${row.cohort}`} data-testid={`button-edit-capacity-${i}`}><Pencil size={14}/></button></td></tr>; })}</tbody></table></div> : <InlineEmpty label="Data kapasitas belum tersedia"/>}</section>
      <section className="panel cohort-panel"><div className="panel-title"><div><div className="eyebrow">PERJALANAN ANGKATAN</div><h3>Progres dari seleksi hingga kelulusan</h3></div><BookOpen size={18}/></div>{data.cohortTrend.length ? <div className="trend-grid">{data.cohortTrend.map(row => <div className="trend-row" key={row.cohort}><div className="trend-year">{row.cohort}</div><div className="trend-bars">{[['Pendaftar',row.applicants,'var(--ink)'],['Penerima',row.recipients,'var(--teal)'],['Lulus',row.graduates,'var(--ochre)'],['Putus',row.dropouts,'var(--coral)']].map(([label,value,color]) => <div className="trend-item" key={String(label)}><span>{label}</span><div className="trend-track"><i style={{ width: `${Math.max(3, Number(value) / Math.max(row.applicants,1) * 100)}%`, background: String(color) }}/></div><b>{number(Number(value))}</b></div>)}</div></div>)}</div> : <InlineEmpty label="Belum ada data angkatan"/>}</section>
      <div className="dashboard-endnote"><span className="endnote-rule"/><span>Angka diperbarui mengikuti data sumber program.</span><span className="mono">BPDPKS / {data.cohort}</span></div>
    </> : null}
    {capacityDraft && <CapacityDialog draft={capacityDraft} pending={saveCapacity.isPending} error={saveCapacity.isError} setDraft={setCapacityDraft} onClose={()=>setCapacityDraft(null)} onSubmit={submitCapacity}/>}
  </div>;
}
function Metric({ icon: Icon, label, value, detail, tone, trend }: { icon: typeof Activity; label: string; value: string; detail: string; tone: string; trend?: string }) { return <div className="metric-card"><div className="metric-top"><span className={`metric-icon ${tone}`}><Icon size={17}/></span>{trend && <span className={`metric-trend ${trend}`}>{trend === 'up' ? <ArrowUpRight size={15}/> : <ArrowDownRight size={15}/>}</span>}</div><div className="metric-label">{label}</div><strong>{value}</strong><small>{detail}</small></div>; }
function Flag({ color, count, title, expl }: { color: string; count?: number; title: string; expl: string }) { return <article className={`flag-card flag-${color}`}><div className="flag-top"><span className="flag-dot"/><span className="flag-count">{number(count)}</span></div><h3>{title}</h3><p>{expl}</p></article>; }
function BarRow({ label, count, max, idx }: { label: string; count: number; max: number; idx: number }) { return <div className="bar-row"><span className="bar-label">{label}</span><div className="bar-track"><i className={`bar-fill bar-${idx}`} style={{ width: `${Math.max(3, count / max * 100)}%` }}/></div><b>{number(count)}</b></div>; }
function InlineEmpty({ label }: { label: string }) { return <div className="inline-empty"><span className="empty-dash"/> {label}</div>; }
function CapacityDialog({ draft, pending, error, setDraft, onClose, onSubmit }: { draft: CapacityInput; pending: boolean; error: boolean; setDraft: (value: CapacityInput | null) => void; onClose: () => void; onSubmit: (event: FormEvent) => void }) {
  const isEdit = Boolean(draft.campus && draft.program);
  return <div className="modal-backdrop" onMouseDown={event => { if (event.target === event.currentTarget) onClose(); }}>
    <section className="dialog capacity-dialog" role="dialog" aria-modal="true" aria-labelledby="capacity-dialog-title">
      <div className="dialog-heading"><div><div className="eyebrow">DAYA TAMPUNG PROGRAM</div><h2 id="capacity-dialog-title">{isEdit ? 'Ubah kapasitas' : 'Tambah kapasitas'}</h2><p>{isEdit ? 'Perbarui kuota dan alokasi untuk kombinasi program ini.' : 'Tetapkan kapasitas, angkatan, dan batas anggaran program.'}</p></div><button className="icon-btn" onClick={onClose} aria-label="Tutup dialog kapasitas"><X size={18}/></button></div>
      <form onSubmit={onSubmit}><div className="dialog-body"><div className="form-grid">
        <Field label="Nama kampus"><input required minLength={2} maxLength={180} value={draft.campus} onChange={event=>setDraft({...draft,campus:event.target.value})} placeholder="Nama perguruan tinggi"/></Field>
        <Field label="Provinsi kampus (opsional)"><input maxLength={100} value={draft.campusProvince ?? ''} onChange={event=>setDraft({...draft,campusProvince:event.target.value})} placeholder="Provinsi"/></Field>
        <Field label="Program studi"><input required minLength={2} maxLength={180} value={draft.program} onChange={event=>setDraft({...draft,program:event.target.value})} placeholder="Nama program studi"/></Field>
        <Field label="Jenjang"><select value={draft.degree} onChange={event=>setDraft({...draft,degree:event.target.value as CapacityInput['degree']})}>{['D1','D2','D3','D4/S1'].map(degree=><option key={degree} value={degree}>{degree}</option>)}</select></Field>
        <Field label="Angkatan"><input type="number" min="2000" max="2100" required value={draft.cohort} onChange={event=>setDraft({...draft,cohort:Number(event.target.value)})}/></Field>
        <Field label="Kuota"><input type="number" min="0" required value={draft.quota} onChange={event=>setDraft({...draft,quota:Number(event.target.value)})}/></Field>
        <Field label="Pagu anggaran (rupiah)"><input type="number" min="0" required value={draft.budgetCeiling} onChange={event=>setDraft({...draft,budgetCeiling:Number(event.target.value)})}/></Field>
      </div>{error && <div className="form-error"><AlertCircle size={15}/> Kapasitas gagal disimpan. Periksa data—kombinasi program dan angkatan mungkin sudah ada.</div>}</div><div className="dialog-actions"><button type="button" className="button secondary" onClick={onClose}>Batal</button><button className="button primary" type="submit" disabled={pending}>{pending ? 'Menyimpan…' : <><Check size={15}/> Simpan kapasitas</>}</button></div></form>
    </section>
  </div>;
}

const blankApplicant: ApplicantInput = { registrationNumber: '', name: '', gender: 'L', birthDate: '', schoolName: '', schoolType: 'SMA', province: '', regency: '', selectionPath: 'pekebun', selectionStatus: 'mendaftar', campus: '', program: '', degree: 'D4/S1', cohort: new Date().getFullYear() };
type AcademicDraft = { semester: string; ip: string };
type FundingDraft = { semester: string; tuitionPaid: string; stipend: string; books: string; transport: string; graduationCost: string };
type EditDraft = { selectionStatus: string; gpa: string; activeSemester: string; studyStatus: string; graduationDate: string; studyDurationMonths: string; internshipLocation: string; internshipPeriod: string; certificateIssued: boolean; placementStatus: string; academicRecords: AcademicDraft[]; fundingBySemester: FundingDraft[] };
function Applicants() {
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [province, setProvince] = useState('');
  const [cohort, setCohort] = useState('');
  const [modal, setModal] = useState<'create' | 'edit' | null>(null);
  const [selected, setSelected] = useState<ScholarshipApplicant | null>(null);
  const [form, setForm] = useState<ApplicantInput>(blankApplicant);
  const [editForm, setEditForm] = useState<EditDraft | null>(null);
  const queryClient = useQueryClient();
  const params = useMemo(() => ({ search: search || undefined, status: status ? status as 'mendaftar' : undefined, province: province || undefined, cohort: cohort ? Number(cohort) : undefined }), [search,status,province,cohort]);
  const query = useGetScholarshipApplicants(params);
  const create = useCreateScholarshipApplicant();
  const update = useUpdateScholarshipApplicant();
  const allRows = query.data?.items ?? [];
  const provinces = [...new Set([...allRows.map(x => x.province), province].filter(Boolean))].sort();
  const close = () => { setModal(null); setSelected(null); };
  const refresh = () => { queryClient.invalidateQueries({ queryKey: getGetScholarshipApplicantsQueryKey() }); queryClient.invalidateQueries({ queryKey: getGetScholarshipDashboardQueryKey() }); };
  const submitCreate = (e: FormEvent) => { e.preventDefault(); create.mutate({ data: form }, { onSuccess: () => { refresh(); close(); setForm(blankApplicant); } }); };
  const submitEdit = (e: FormEvent) => {
    e.preventDefault();
    if (!selected || !editForm) return;
    const payload: ApplicantUpdate = {
      selectionStatus: editForm.selectionStatus as ApplicantUpdate['selectionStatus'],
      gpa: editForm.gpa === '' ? null : Number(editForm.gpa),
      activeSemester: editForm.activeSemester === '' ? null : Number(editForm.activeSemester),
      studyStatus: editForm.studyStatus as ApplicantUpdate['studyStatus'],
      graduationDate: editForm.graduationDate || null,
      studyDurationMonths: editForm.studyDurationMonths === '' ? null : Number(editForm.studyDurationMonths),
      internshipLocation: editForm.internshipLocation || null,
      internshipPeriod: editForm.internshipPeriod || null,
      certificateIssued: editForm.certificateIssued,
      placementStatus: editForm.placementStatus as ApplicantUpdate['placementStatus'],
      academicRecords: editForm.academicRecords.map(row => ({ semester: Number(row.semester), ip: Number(row.ip) })),
      fundingBySemester: editForm.fundingBySemester.map(row => ({
        semester: Number(row.semester), tuitionPaid: Number(row.tuitionPaid || 0),
        stipendAndBooks: Number(row.stipend || 0) + Number(row.books || 0),
        stipend: Number(row.stipend || 0), books: Number(row.books || 0),
        transport: Number(row.transport || 0), graduationCost: Number(row.graduationCost || 0),
      })),
    };
    update.mutate({ id: selected.id, data: payload }, { onSuccess: () => { refresh(); close(); } });
  };
  const startEdit = (row: ScholarshipApplicant) => {
    setSelected(row);
    setEditForm({
      selectionStatus: row.selectionStatus, gpa: row.gpa == null ? '' : String(row.gpa),
      activeSemester: row.activeSemester == null ? '' : String(row.activeSemester), studyStatus: row.studyStatus,
      graduationDate: row.graduationDate ?? '', studyDurationMonths: row.studyDurationMonths == null ? '' : String(row.studyDurationMonths),
      internshipLocation: row.internshipLocation ?? '', internshipPeriod: row.internshipPeriod ?? '',
      certificateIssued: row.certificateIssued, placementStatus: row.placementStatus,
      academicRecords: (row.academicRecords ?? []).map(record => ({ semester: String(record.semester), ip: String(record.ip) })),
      fundingBySemester: (row.fundingBySemester ?? []).map(record => ({ semester: String(record.semester), tuitionPaid: String(record.tuitionPaid), stipend: String(record.stipend), books: String(record.books), transport: String(record.transport), graduationCost: String(record.graduationCost) })),
    });
    setModal('edit');
  };
  const patchEdit = (patch: Partial<EditDraft>) => setEditForm(current => current ? { ...current, ...patch } : current);
  const patchAcademic = (index: number, patch: Partial<AcademicDraft>) => patchEdit({ academicRecords: (editForm?.academicRecords ?? []).map((row, i) => i === index ? { ...row, ...patch } : row) });
  const patchFunding = (index: number, patch: Partial<FundingDraft>) => patchEdit({ fundingBySemester: (editForm?.fundingBySemester ?? []).map((row, i) => i === index ? { ...row, ...patch } : row) });
  const setApplicant = (key: keyof ApplicantInput, value: string) => setForm(old => ({ ...old, [key]: key === 'cohort' ? Number(value) : value }));
  return <div className="page-wrap">
    <PageHeading eyebrow="DATA PENGELOLAAN" title="Pendaftar & penerima" subtitle="Telusuri rekam pendaftaran, status seleksi, kemajuan studi, dan penyaluran beasiswa." actions={<button className="button primary" onClick={() => { setForm(blankApplicant); setModal('create'); }} data-testid="button-add-applicant"><Plus size={16}/> Tambah pendaftar</button>}/>
    <div className="applicants-summary"><div className="summary-mark"><Users size={19}/></div><div><strong>{query.data ? number(query.data.total) : '—'}</strong><span>rekam pendaftar dan penerima</span></div><div className="summary-divider"/><span className="summary-explain">Gunakan pencarian dan filter untuk mempersempit daftar.</span><span className="summary-list-icon"><SlidersHorizontal size={17}/></span></div>
    <section className="panel applicants-panel">
      <div className="filter-bar"><label className="search-field"><Search size={17}/><input aria-label="Cari pendaftar" data-testid="input-search-applicants" placeholder="Cari nama atau nomor registrasi…" value={search} onChange={e => setSearch(e.target.value)}/>{search && <button className="clear-search" onClick={() => setSearch('')} aria-label="Hapus pencarian"><X size={15}/></button>}</label>
        <label className="filter-select"><span>Status seleksi</span><select value={status} onChange={e => setStatus(e.target.value)} data-testid="select-status"><option value="">Semua status</option>{['mendaftar','lolos_administrasi','lolos_tes','diterima','mengundurkan_diri'].map(x => <option key={x} value={x}>{display(x)}</option>)}</select><ChevronDown size={14}/></label>
        <label className="filter-select"><span>Provinsi</span><select value={province} onChange={e => setProvince(e.target.value)} data-testid="select-province"><option value="">Semua provinsi</option>{provinces.map(x => <option key={x} value={x}>{x}</option>)}</select><ChevronDown size={14}/></label>
        <label className="filter-select cohort-filter"><span>Angkatan</span><input type="number" placeholder="Semua" value={cohort} onChange={e => setCohort(e.target.value)} data-testid="input-cohort-filter"/></label>
      </div>
      {query.isLoading ? <div className="list-loading"><div className="skeleton-row"/><div className="skeleton-row"/><div className="skeleton-row"/></div> : query.isError ? <LoadError onRetry={() => query.refetch()}/> : allRows.length === 0 ? <div className="empty-state"><div className="empty-illustration"><Search size={23}/></div><h3>{search || status || province || cohort ? 'Tidak ada hasil yang cocok' : 'Belum ada data pendaftar'}</h3><p>{search || status || province || cohort ? 'Coba ubah kata kunci atau filter yang dipilih.' : 'Mulai dengan menambahkan rekam pendaftar pertama.'}</p>{search || status || province || cohort ? <button className="button secondary" onClick={() => { setSearch(''); setStatus(''); setProvince(''); setCohort(''); }}>Hapus semua filter</button> : <button className="button primary" onClick={() => setModal('create')}><Plus size={15}/> Tambah pendaftar</button>}</div> : <>
        <div className="table-wrap applicant-table-wrap"><table className="applicant-table"><thead><tr><th>Pendaftar</th><th>Asal & jalur</th><th>Status seleksi</th><th>Kampus / program</th><th>Progres studi</th><th>Kelayakan</th><th></th></tr></thead><tbody>{allRows.map(row => <tr key={row.id} data-testid={`row-applicant-${row.id}`}><td><div className="person-cell"><div className="initials">{row.name.split(/\s+/).slice(0,2).map(s=>s[0]).join('').toUpperCase()}</div><div><b>{row.name}</b><span className="mono reg-number">{row.registrationNumber}</span></div></div></td><td><b className="origin-main">{row.regency}, {row.province}</b><span className="origin-sub">{pathLabels[row.selectionPath] ?? row.selectionPath}</span></td><td><span className={`status-pill status-${row.selectionStatus}`}>{display(row.selectionStatus)}</span></td><td><b className="origin-main">{row.campus}</b><span className="origin-sub">{row.program} · {row.degree}</span></td><td><div className="study-cell"><b>{display(row.studyStatus)}</b><span>{row.activeSemester ? `Semester ${row.activeSemester}` : 'Semester belum tercatat'}{row.gpa != null ? ` · IPK ${row.gpa.toFixed(2)}` : ''}</span></div></td><td><span className={cls('eligibility', row.ageEligible ? 'eligible' : 'ineligible')}><i/>{row.ageEligible ? 'Usia sesuai' : 'Tinjau usia'}</span><span className="age-sub">{row.ageAtRegistration} tahun saat daftar</span></td><td><button className="row-action" onClick={() => startEdit(row)} aria-label={`Perbarui data ${row.name}`} data-testid={`button-edit-${row.id}`}><ArrowRight size={16}/></button></td></tr>)}</tbody></table></div>
        <div className="table-footer"><span>Menampilkan <b>{number(allRows.length)}</b> dari <b>{number(query.data?.total)}</b> rekam</span><span className="footer-source"><BadgeCheck size={14}/> Sumber: data layanan program</span></div>
      </>}
    </section>
    {(modal === 'create' || modal === 'edit') && <div className="modal-backdrop" onMouseDown={e => { if(e.target === e.currentTarget) close(); }}><section className="dialog" role="dialog" aria-modal="true" aria-labelledby="dialog-title"><div className="dialog-heading"><div><div className="eyebrow">{modal === 'create' ? 'PENDAFTARAN BARU' : 'PEMUTAKHIRAN DATA'}</div><h2 id="dialog-title">{modal === 'create' ? 'Tambah pendaftar' : 'Perbarui rekam penerima'}</h2><p>{modal === 'create' ? 'Lengkapi informasi dasar untuk mencatat pendaftar.' : `${selected?.name} · ${selected?.registrationNumber}`}</p></div><button className="icon-btn" onClick={close} aria-label="Tutup dialog"><X size={18}/></button></div>
      {modal === 'create' ? <form onSubmit={submitCreate}><div className="dialog-body"><div className="form-section-label">IDENTITAS & ASAL</div><div className="form-grid">
        <Field label="Nomor registrasi"><input required minLength={4} maxLength={30} value={form.registrationNumber} onChange={e=>setApplicant('registrationNumber',e.target.value)} placeholder="Contoh: BP-2025-001"/></Field>
        <Field label="Nama lengkap"><input required minLength={2} maxLength={150} value={form.name} onChange={e=>setApplicant('name',e.target.value)} placeholder="Nama sesuai dokumen"/></Field>
        <Field label="Jenis kelamin"><select value={form.gender} onChange={e=>setApplicant('gender',e.target.value)}><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></Field>
        <Field label="Tanggal lahir"><input type="date" required value={form.birthDate} onChange={e=>setApplicant('birthDate',e.target.value)}/></Field>
        <Field label="Asal sekolah"><input required value={form.schoolName} onChange={e=>setApplicant('schoolName',e.target.value)} placeholder="Nama sekolah"/></Field>
        <Field label="Jenis sekolah"><select value={form.schoolType} onChange={e=>setApplicant('schoolType',e.target.value)}><option>SMA</option><option>SMK</option><option>MA</option></select></Field>
        <Field label="Provinsi"><input required value={form.province} onChange={e=>setApplicant('province',e.target.value)} placeholder="Provinsi"/></Field>
        <Field label="Kabupaten / kota"><input required value={form.regency} onChange={e=>setApplicant('regency',e.target.value)} placeholder="Kabupaten atau kota"/></Field>
      </div><div className="form-section-label">PILIHAN PROGRAM</div><div className="form-grid">
        <Field label="Jalur seleksi"><select value={form.selectionPath} onChange={e=>setApplicant('selectionPath',e.target.value)}>{Object.keys(pathLabels).map(k=><option key={k} value={k}>{pathLabels[k]}</option>)}</select></Field>
        <div className="field"><span>Status seleksi</span><div className="locked-value">Mendaftar</div></div>
        <Field label="Kampus"><input required value={form.campus} onChange={e=>setApplicant('campus',e.target.value)} placeholder="Nama perguruan tinggi"/></Field>
        <Field label="Program studi"><input required value={form.program} onChange={e=>setApplicant('program',e.target.value)} placeholder="Nama program studi"/></Field>
        <Field label="Jenjang"><select value={form.degree} onChange={e=>setApplicant('degree',e.target.value)}>{['D1','D2','D3','D4/S1'].map(x=><option key={x}>{x}</option>)}</select></Field>
        <Field label="Angkatan"><input type="number" min="2000" max="2100" required value={form.cohort} onChange={e=>setApplicant('cohort',e.target.value)}/></Field>
      </div>{create.isError && <div className="form-error"><AlertCircle size={15}/> Pendaftaran gagal disimpan. Periksa data dan coba lagi.</div>}</div><div className="dialog-actions"><button type="button" className="button secondary" onClick={close}>Batal</button><button className="button primary" type="submit" disabled={create.isPending}>{create.isPending ? 'Menyimpan…' : <><Check size={15}/> Simpan pendaftar</>}</button></div></form> : editForm && <form onSubmit={submitEdit}><div className="applicant-context"><div><span>IDENTITAS</span><b>{selected?.gender === 'L' ? 'Laki-laki' : 'Perempuan'} · {selected?.birthDate ? new Date(selected.birthDate).toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' }) : 'Tanggal lahir —'}</b></div><div><span>ASAL SEKOLAH</span><b>{selected?.schoolName} · {selected?.schoolType}</b></div><div><span>KELAYAKAN USIA</span><b className={selected?.ageEligible ? 'context-ok' : 'context-review'}>{selected?.ageEligible ? 'Sesuai aturan' : 'Perlu ditinjau'} · {selected?.ageAtRegistration} tahun</b></div><div><span>JALUR & ANGKATAN</span><b>{pathLabels[selected?.selectionPath ?? ''] ?? selected?.selectionPath} · {selected?.cohort}</b></div></div><div className="dialog-body"><div className="form-section-label">SELEKSI & AKADEMIK</div><div className="form-grid">
        <Field label="Status seleksi"><select value={editForm.selectionStatus} onChange={e=>patchEdit({selectionStatus:e.target.value})}>{['mendaftar','lolos_administrasi','lolos_tes','diterima','mengundurkan_diri'].map(x=><option key={x} value={x}>{display(x)}</option>)}</select></Field>
        <Field label="Status studi"><select value={editForm.studyStatus} onChange={e=>patchEdit({studyStatus:e.target.value})}>{['aktif','cuti','putus','lulus'].map(x=><option key={x} value={x}>{display(x)}</option>)}</select></Field>
        <Field label="IPK ringkasan"><input type="number" min="0" max="4" step=".01" value={editForm.gpa} onChange={e=>patchEdit({gpa:e.target.value})} placeholder="Belum tercatat"/></Field>
        <Field label="Semester aktif"><input type="number" min="1" value={editForm.activeSemester} onChange={e=>patchEdit({activeSemester:e.target.value})} placeholder="Belum tercatat"/></Field>
        <Field label="Tanggal kelulusan"><input type="date" value={editForm.graduationDate} onChange={e=>patchEdit({graduationDate:e.target.value})}/></Field>
        <Field label="Durasi studi (bulan)"><input type="number" min="0" value={editForm.studyDurationMonths} onChange={e=>patchEdit({studyDurationMonths:e.target.value})} placeholder="Belum tercatat"/></Field>
      </div>
      <div className="history-heading"><div><div className="form-section-label">RIWAYAT AKADEMIK</div><p>Nilai indeks prestasi per semester.</p></div><button type="button" className="button secondary compact-button" onClick={()=>patchEdit({academicRecords:[...editForm.academicRecords,{semester:String(editForm.academicRecords.length+1),ip:''}]})}><Plus size={13}/> Tambah semester</button></div>
      {editForm.academicRecords.length ? <div className="semester-list">{editForm.academicRecords.map((record,index)=><div className="semester-row academic-row" key={`academic-${index}`}><span className="semester-index">{String(index+1).padStart(2,'0')}</span><Field label="Semester"><input type="number" min="1" required value={record.semester} onChange={e=>patchAcademic(index,{semester:e.target.value})}/></Field><Field label="IP"><input type="number" min="0" max="4" step=".01" required value={record.ip} onChange={e=>patchAcademic(index,{ip:e.target.value})}/></Field><button type="button" className="remove-row" aria-label={`Hapus riwayat akademik semester ${record.semester}`} onClick={()=>patchEdit({academicRecords:editForm.academicRecords.filter((_,i)=>i!==index)})}><X size={14}/></button></div>)}</div> : <div className="history-empty">Belum ada riwayat akademik. Tambahkan semester untuk mencatat IP.</div>}
      <div className="history-heading funding-heading"><div><div className="form-section-label">PENDANAAN PER SEMESTER</div><p>Realisasi biaya dicatat per semester. Tunjangan & buku dihitung otomatis dari komponennya.</p></div><button type="button" className="button secondary compact-button" onClick={()=>patchEdit({fundingBySemester:[...editForm.fundingBySemester,{semester:String(editForm.fundingBySemester.length+1),tuitionPaid:'0',stipend:'0',books:'0',transport:'0',graduationCost:'0'}]})}><Plus size={13}/> Tambah semester</button></div>
      {editForm.fundingBySemester.length ? <div className="funding-list">{editForm.fundingBySemester.map((row,index)=><div className="funding-row" key={`funding-${index}`}><div className="funding-row-head"><span className="semester-index">{String(index+1).padStart(2,'0')}</span><Field label="Semester"><input type="number" min="1" required value={row.semester} onChange={e=>patchFunding(index,{semester:e.target.value})}/></Field><button type="button" className="remove-row" aria-label={`Hapus pendanaan semester ${row.semester}`} onClick={()=>patchEdit({fundingBySemester:editForm.fundingBySemester.filter((_,i)=>i!==index)})}><X size={14}/></button></div><div className="funding-fields"><Field label="Biaya pendidikan"><input type="number" min="0" required value={row.tuitionPaid} onChange={e=>patchFunding(index,{tuitionPaid:e.target.value})}/></Field><Field label="Tunjangan"><input type="number" min="0" required value={row.stipend} onChange={e=>patchFunding(index,{stipend:e.target.value})}/></Field><Field label="Buku"><input type="number" min="0" required value={row.books} onChange={e=>patchFunding(index,{books:e.target.value})}/></Field><Field label="Transportasi"><input type="number" min="0" required value={row.transport} onChange={e=>patchFunding(index,{transport:e.target.value})}/></Field><Field label="Biaya kelulusan"><input type="number" min="0" required value={row.graduationCost} onChange={e=>patchFunding(index,{graduationCost:e.target.value})}/></Field><div className="computed-total"><span>Tunjangan & buku</span><b>{money(Number(row.stipend || 0)+Number(row.books || 0))}</b></div></div></div>)}</div> : <div className="history-empty">Belum ada realisasi pendanaan. Tambahkan semester untuk mencatat komponen biaya.</div>}
      <div className="form-section-label section-spacer">PENEMPATAN & DOKUMEN</div><div className="form-grid">
        <Field label="Lokasi magang"><input value={editForm.internshipLocation} onChange={e=>patchEdit({internshipLocation:e.target.value})} placeholder="Lokasi magang"/></Field>
        <Field label="Periode magang"><input value={editForm.internshipPeriod} onChange={e=>patchEdit({internshipPeriod:e.target.value})} placeholder="Contoh: Jan–Mar 2025"/></Field>
        <Field label="Status penempatan"><select value={editForm.placementStatus} onChange={e=>patchEdit({placementStatus:e.target.value})}>{['belum_lulus','belum_ditempatkan','bekerja','wirausaha'].map(x=><option key={x} value={x}>{display(x)}</option>)}</select></Field>
        <label className="check-field"><input type="checkbox" checked={editForm.certificateIssued} onChange={e=>patchEdit({certificateIssued:e.target.checked})}/><span>Sertifikat telah diterbitkan</span></label>
      </div>{update.isError && <div className="form-error"><AlertCircle size={15}/> Perubahan gagal disimpan. Coba kembali.</div>}</div><div className="dialog-actions"><button type="button" className="button secondary" onClick={close}>Batal</button><button className="button primary" type="submit" disabled={update.isPending}>{update.isPending ? 'Menyimpan…' : <><Check size={15}/> Simpan perubahan</>}</button></div></form>}
    </section></div>}
  </div>;
}
function Field({ label, children }: { label: string; children: ReactNode }) { return <label className="field"><span>{label}</span>{children}</label>; }
export default App;
