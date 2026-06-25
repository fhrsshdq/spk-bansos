const navDashboard = document.getElementById('nav-dashboard');
const navData = document.getElementById('nav-data');
const navCriteria = document.getElementById('nav-criteria');
const navPenilaian = document.getElementById('nav-penilaian');
const navHasil = document.getElementById('nav-hasil');
const navPanduan = document.getElementById('nav-panduan');
const navAdmin = document.getElementById('nav-admin');

const viewDashboard = document.getElementById('view-dashboard');
const viewData = document.getElementById('view-data');
const viewCriteria = document.getElementById('view-criteria');
const viewPenilaian = document.getElementById('view-penilaian');
const viewHasil = document.getElementById('view-hasil');
const viewPanduan = document.getElementById('view-panduan');
const viewAdmin = document.getElementById('view-admin');

const totalWargaEl = document.getElementById('total-warga');
const inputKuota = document.getElementById('input-kuota');
const inputNominal = document.getElementById('input-nominal');
const totalAnggaran = document.getElementById('total-anggaran');

// Load saved kuota if exists
const savedKuota = localStorage.getItem('spk_kuota');
if (savedKuota && inputKuota) {
    inputKuota.value = savedKuota;
}

// Load saved nominal if exists
const savedNominal = localStorage.getItem('spk_nominal');
if (savedNominal && inputNominal) {
    inputNominal.value = savedNominal;
}

const rankingTableBody = document.getElementById('ranking-body');
const dataTableBody = document.getElementById('data-body');
const searchInput = document.getElementById('search-input');

const totalWeightDisplay = document.getElementById('total-weight-display');
const weightWarning = document.getElementById('weight-warning');

const slidersWeight = document.querySelectorAll('.weight-slider');
const inputsWeight = document.querySelectorAll('.weight-input-val');

let allDataWarga = [];
let globalRankedData = [];
let globalFilteredDataWarga = []; // For the Data Warga view filtering
let myChart; // For dashboard chart
let showAllPrioritas = false; // Flag to show all in prioritas list
let globalMatrixKeputusan = [];
let globalMatrixNormalisasi = [];
let currentFilter = 'all'; // all, lolos, cadangan
let myDistChart = null;
let currentKuota = parseInt(inputKuota?.value) || 3;
let currentNominal = parseInt(inputNominal?.value) || 600000;

// Sidebar Toggle Logic
const sidebarToggleBtn = document.getElementById('sidebar-toggle');
const sidebarEl = document.querySelector('.sidebar');
const mainContentEl = document.querySelector('.main-content');
const topbarEl = document.querySelector('.topbar');
const brandLogoEl = document.querySelector('.brand-logo');
const sidebarFooterEl = document.querySelector('.sidebar-footer');

if (topbarEl) {
    // Prevent scrolling the main page when cursor is over the topbar
    topbarEl.addEventListener('wheel', (e) => {
        e.preventDefault();
    }, { passive: false });
}

if (brandLogoEl) {
    brandLogoEl.addEventListener('wheel', (e) => {
        e.preventDefault();
    }, { passive: false });
}

if (sidebarFooterEl) {
    sidebarFooterEl.addEventListener('wheel', (e) => {
        e.preventDefault();
    }, { passive: false });
}

if (sidebarToggleBtn && sidebarEl && mainContentEl) {
    sidebarToggleBtn.addEventListener('click', () => {
        sidebarEl.classList.toggle('collapsed');
        mainContentEl.classList.toggle('expanded');
        
        // For mobile:
        if(window.innerWidth <= 768) {
            sidebarEl.classList.toggle('open');
        }
    });
}

// Navigation Logic
navDashboard.addEventListener('click', () => switchView(viewDashboard, navDashboard));
navData.addEventListener('click', () => switchView(viewData, navData));
navCriteria.addEventListener('click', () => switchView(viewCriteria, navCriteria));
if(navPenilaian) navPenilaian.addEventListener('click', () => switchView(viewPenilaian, navPenilaian));
if(navHasil) navHasil.addEventListener('click', () => switchView(viewHasil, navHasil));
if(navPanduan) navPanduan.addEventListener('click', () => switchView(viewPanduan, navPanduan));
if(navAdmin && viewAdmin) navAdmin.addEventListener('click', () => switchView(viewAdmin, navAdmin));


function switchView(view, nav) {
    document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.nav-links li').forEach(el => el.classList.remove('active'));
    
    view.classList.add('active');
    nav.parentElement.classList.add('active');
    
    // Close sidebar on mobile after clicking a link
    if (window.innerWidth <= 768) {
        const sidebarEl = document.querySelector('.sidebar');
        if (sidebarEl) sidebarEl.classList.remove('open');
    }
}

function formatRupiah(number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
}

// Fetch Data from PHP API
let fetchTimeout;
async function fetchData() {
    try {
        currentKuota = parseInt(inputKuota?.value) || 3;
        currentNominal = parseInt(inputNominal?.value) || 600000;

        // Build URL dynamically based on .weight-input-val
        let params = new URLSearchParams();
        params.append('kuota', currentKuota);
        
        document.querySelectorAll('.weight-input-val').forEach(inp => {
            // inp.id is like 'w-C1', so we pass 'w_C1'
            params.append(inp.id.replace('-', '_'), inp.value);
        });

        const url = `api.php?${params.toString()}`;
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.status === 'success') {
            allDataWarga = data.data_warga;
            totalWargaEl.textContent = data.total_warga;
            
            // Anggaran = Kuota * Nominal
            if(totalAnggaran) totalAnggaran.textContent = formatRupiah(currentKuota * currentNominal);
            
            globalRankedData = data.ranked_data;
            if(data.matrix_keputusan) globalMatrixKeputusan = data.matrix_keputusan;
            if(data.matrix_normalisasi) globalMatrixNormalisasi = data.matrix_normalisasi;
            
            updateChartAndActivity(globalRankedData);
            applyFilter();
            
            // Pass kriteria_data to render functions so they know the columns
            renderDataWarga(allDataWarga, data.kriteria_data);
            renderMatriks(data.kriteria_data);
        }
    } catch (error) {
        console.error("Error fetching data:", error);
        rankingTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--danger);">Gagal memuat data dari PHP. Pastikan web server menyala.</td></tr>`;
    }
}

// Render Tables
function renderRankingTableOnly(dataToRender) {
    rankingTableBody.innerHTML = '';
    
    if(dataToRender.length === 0) {
        rankingTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Tidak ada data pada kategori ini.</td></tr>`;
        return;
    }
    
    dataToRender.forEach((d) => {
        const originalRank = globalRankedData.findIndex(item => item === d) + 1;
        
        let statusBadge = '';
        if (originalRank <= currentKuota) {
            statusBadge = '<span class="status-badge status-lolos">Lolos - Penerima Bansos</span>';
        } else if (d.skorAkhir >= 60) {
            statusBadge = '<span class="status-badge status-cadangan" style="background:#fef08a; color:#854d0e; border:1px solid #fde047;">Menengah - Cadangan</span>';
        } else {
            statusBadge = '<span class="status-badge status-tidak-layak" style="background:#fee2e2; color:#b91c1c; border:1px solid #fecaca;">Mampu - Tidak Layak</span>';
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${d.nama}</strong></td>
            <td>${d.nokk}</td>
            <td style="font-weight:700; color:var(--primary);">${d.skorAkhir}</td>
            <td>${statusBadge}</td>
        `;
        rankingTableBody.appendChild(tr);
    });
}

function renderDataWarga(dataToRender, kriteriaData) {
    if (!dataTableBody) return;
    dataTableBody.innerHTML = '';
    
    // If we have kriteriaData, we should probably update the table header as well if it was dynamic, 
    // but the header is rendered via PHP. Here we just match the columns.
    // However, if we're live searching, we might not have kriteriaData passed if it's not cached.
    // Let's cache it.
    if(kriteriaData) window.cachedKriteria = kriteriaData;
    let kData = window.cachedKriteria || [];
    
    dataToRender.forEach((d) => {
        const tr = document.createElement('tr');
        
        let tdScores = '';
        kData.forEach(k => {
            tdScores += `<td>${d.scores[k.kode] || '-'}</td>`;
        });
        
        tr.innerHTML = `
            <td><strong>${d.nama}</strong></td>
            <td>${d.nokk}</td>
            ${tdScores}
        `;
        dataTableBody.appendChild(tr);
    });
}

// Live Search
if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        const filtered = allDataWarga.filter(d => d.nama.toLowerCase().includes(term) || String(d.nokk).includes(term));
        renderDataWarga(filtered, window.cachedKriteria);
        
        // Switch to data tab if searching
        if(document.querySelector('.view-section.active').id !== 'view-data') {
            switchView(viewData, navData);
        }
    });
}

// Calculate Weights
function calculateTotalWeight() {
    let total = 0;
    inputsWeight.forEach(input => {
        total += parseInt(input.value || 0);
    });
    
    totalWeightDisplay.textContent = `Total: ${total}%`;
    if (total === 100) {
        totalWeightDisplay.style.color = '#10b981';
    } else {
        totalWeightDisplay.style.color = '#ef4444';
    }
}

// Logic for "Hasil Seleksi" (Hitung SAW Button)
const btnHitungSaw = document.getElementById('btn-hitung-saw');
const hasilPlaceholder = document.getElementById('hasil-placeholder');
const hasilFilters = document.getElementById('hasil-filters');
const hasilTableContainer = document.getElementById('hasil-table-container');
const btnPrint = document.getElementById('btn-print');

if(btnHitungSaw) {
    btnHitungSaw.addEventListener('click', () => {
        // Show loading state on button
        const originalText = btnHitungSaw.innerHTML;
        btnHitungSaw.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menghitung...';
        btnHitungSaw.disabled = true;

        setTimeout(() => {
            // Restore button
            btnHitungSaw.innerHTML = originalText;
            btnHitungSaw.disabled = false;

            // Switch visibility
            if(hasilPlaceholder) hasilPlaceholder.style.display = 'none';
            if(hasilFilters) hasilFilters.style.display = 'block';
            if(hasilTableContainer) hasilTableContainer.style.display = 'block';
            if(btnPrint) btnPrint.style.display = 'inline-block';
            if(document.getElementById('btn-detail-matriks')) document.getElementById('btn-detail-matriks').style.display = 'inline-block';
            
            // Re-render table to ensure data is displayed
            applyFilter();
        }, 800);
    });
}

// Fix Filters for Hasil view
const filterNama = document.getElementById('filter-nama');
if(filterNama) {
    filterNama.addEventListener('input', applyFilter);
}

// Initial Fetch
fetchData();

// Event Listeners for Weights
inputsWeight.forEach(input => {
    input.addEventListener('input', (e) => {
        const targetId = e.target.id;
        const slider = document.querySelector(`input[type="range"][data-target="${targetId}"]`);
        if (slider) slider.value = e.target.value;
        calculateTotalWeight();
    });
});

slidersWeight.forEach(slider => {
    slider.addEventListener('input', (e) => {
        const targetId = e.target.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (input) input.value = e.target.value;
        calculateTotalWeight();
    });
});

// Nominal Listener
if (inputNominal) {
    inputNominal.addEventListener('input', () => {
        localStorage.setItem('spk_nominal', inputNominal.value);
        currentNominal = parseInt(inputNominal.value) || 600000;
        if(totalAnggaran) totalAnggaran.textContent = formatRupiah(currentKuota * currentNominal);
    });
}

// Kuota Listener
if (inputKuota) {
    inputKuota.addEventListener('input', () => {
        localStorage.setItem('spk_kuota', inputKuota.value);
        currentKuota = parseInt(inputKuota.value) || 3;
        if(totalAnggaran) totalAnggaran.textContent = formatRupiah(currentKuota * currentNominal);
        clearTimeout(fetchTimeout);
        fetchTimeout = setTimeout(() => {
            fetchData();
        }, 500);
    });
}

// Update Chart and Activity (Bar Chart Design)
function updateChartAndActivity(rankedData) {
    // UPDATE TIMESTAMP
    const lastUpdatedEl = document.getElementById('last-updated');
    if (lastUpdatedEl) {
        const now = new Date();
        const tgl = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        const jam = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        lastUpdatedEl.textContent = `Diperbarui: ${tgl} ${jam}`;
    }

    // CHART UPDATE
    let countLolos = 0;
    let countCadangan = 0;
    let countMampu = 0;

    rankedData.forEach((d, i) => {
        if (i < currentKuota) countLolos++;
        else if (d.skorAkhir >= 60) countCadangan++;
        else countMampu++;
    });

    const ctx = document.getElementById('eligibilityChart').getContext('2d');
    if (myChart) myChart.destroy();
    
    // Gradient for Bar Chart (Solid, Bold Colors)
    let gradientLolos = ctx.createLinearGradient(0, 0, 0, 400);
    gradientLolos.addColorStop(0, '#10b981'); // Solid Emerald
    gradientLolos.addColorStop(1, '#047857'); // Darker Emerald

    let gradientCadangan = ctx.createLinearGradient(0, 0, 0, 400);
    gradientCadangan.addColorStop(0, '#f59e0b'); // Solid Amber
    gradientCadangan.addColorStop(1, '#b45309'); // Darker Amber

    let gradientMampu = ctx.createLinearGradient(0, 0, 0, 400);
    gradientMampu.addColorStop(0, '#ef4444'); // Solid Red
    gradientMampu.addColorStop(1, '#b91c1c'); // Darker Red

    myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Penerima (Lolos)', 'Menengah (Cadangan)', 'Mampu (Gagal)'],
            datasets: [{
                label: 'Jumlah Warga',
                data: [countLolos, countCadangan, countMampu],
                backgroundColor: [gradientLolos, gradientCadangan, gradientMampu],
                borderColor: ['transparent', 'transparent', 'transparent'],
                borderWidth: 0,
                borderRadius: 8,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [2, 4], color: '#e2e8f0' } },
                x: { grid: { display: false } }
            }
        }
    });

    // ACTIVITY FEED UPDATE (Dynamic Mock Data based on actual states)
    const activityList = document.getElementById('activity-list');
    let activitiesHTML = '';
    
    // 1. Data updated
    activitiesHTML += `
        <div class="activity-item">
            <div class="activity-dot dot-blue"></div>
            <div class="activity-content">
                <h4>Data Ranking Diperbarui</h4>
                <p>Sistem SAW selesai memproses ${rankedData.length} warga.</p>
                <span class="activity-time">Baru saja</span>
            </div>
        </div>
    `;

    // 2. Lolos
    if(countLolos > 0) {
        activitiesHTML += `
            <div class="activity-item">
                <div class="activity-dot dot-green"></div>
                <div class="activity-content">
                    <h4>${countLolos} Warga Lolos Seleksi</h4>
                    <p>Warga teratas mendapat status layak bansos sesuai kuota ${currentKuota} orang.</p>
                    <span class="activity-time">Beberapa detik lalu</span>
                </div>
            </div>
        `;
    }

    // 3. Cadangan
    if(countCadangan > 0) {
        activitiesHTML += `
            <div class="activity-item">
                <div class="activity-dot dot-orange"></div>
                <div class="activity-content">
                    <h4>${countCadangan} Warga Kategori Menengah</h4>
                    <p>Karena keterbatasan kuota, warga ini masuk daftar cadangan.</p>
                    <span class="activity-time">Beberapa detik lalu</span>
                </div>
            </div>
        `;
    }

    // 4. Mampu
    if(countMampu > 0) {
        activitiesHTML += `
            <div class="activity-item">
                <div class="activity-dot dot-red"></div>
                <div class="activity-content">
                    <h4>${countMampu} Warga Kategori Mampu</h4>
                    <p>Warga ini dinilai sudah mampu dan tidak berhak menerima bansos.</p>
                    <span class="activity-time">Beberapa detik lalu</span>
                </div>
            </div>
        `;
    }

    activityList.innerHTML = activitiesHTML;

    // UPDATE TOP 5
    const top5Body = document.getElementById('top5-body');
    top5Body.innerHTML = '';
    const top5Data = showAllPrioritas ? rankedData : rankedData.slice(0, 5);
    
    if(top5Data.length === 0) {
        top5Body.innerHTML = `<tr><td colspan="4" style="text-align:center;">Tidak ada data</td></tr>`;
    } else {
        top5Data.forEach((d, i) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span style="font-size:13px; font-weight:600; color:var(--text-dark);">${d.nama}</span></td>
                <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="flex:1; height:6px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                            <div style="width:${Math.min((d.skorAkhir * 100), 100)}%; height:100%; background:var(--primary);"></div>
                        </div>
                        <span style="font-size:12px; font-weight:600; width:35px;">${d.skorAkhir}</span>
                    </div>
                </td>
            `;
            top5Body.appendChild(tr);
        });
    }

    // UPDATE DOUGHNUT (DISTRIBUTION)
    const distCtx = document.getElementById('distChart').getContext('2d');
    if (myDistChart) myDistChart.destroy();
    
    document.getElementById('dist-total').textContent = rankedData.length;

    myDistChart = new Chart(distCtx, {
        type: 'doughnut',
        data: {
            labels: ['Lolos', 'Menengah', 'Mampu'],
            datasets: [{
                data: [countLolos, countCadangan, countMampu],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: { position: 'right', labels: { usePointStyle: true, boxWidth: 8, font: {size: 11} } }
            }
        }
    });
}

// Tab Filter Logic
document.getElementById('btn-filter-all').addEventListener('click', () => { currentFilter = 'all'; applyFilter(); });
document.getElementById('btn-filter-lolos').addEventListener('click', () => { currentFilter = 'lolos'; applyFilter(); });
document.getElementById('btn-filter-cadangan').addEventListener('click', () => { currentFilter = 'cadangan'; applyFilter(); });
if (document.getElementById('btn-filter-mampu')) {
    document.getElementById('btn-filter-mampu').addEventListener('click', () => { currentFilter = 'mampu'; applyFilter(); });
}

function applyFilter() {
    let filteredData = [];
    if (currentFilter === 'all') {
        filteredData = globalRankedData;
    } else if (currentFilter === 'lolos') {
        filteredData = globalRankedData.filter((d, i) => i < currentKuota);
    } else if (currentFilter === 'cadangan') {
        filteredData = globalRankedData.filter((d, i) => i >= currentKuota && d.skorAkhir >= 60);
    } else if (currentFilter === 'mampu') {
        filteredData = globalRankedData.filter((d, i) => i >= currentKuota && d.skorAkhir < 60);
    }
    
    const filterNamaInput = document.getElementById('filter-nama');
    if (filterNamaInput) {
        const term = filterNamaInput.value.toLowerCase();
        if (term) {
            filteredData = filteredData.filter(d => d.nama.toLowerCase().includes(term) || String(d.nokk).includes(term));
        }
    }
    
    renderRankingTableOnly(filteredData);
}

function renderMatriks(kriteriaData) {
    const tbodyX = document.getElementById('tbody-matriks-x');
    const tbodyR = document.getElementById('tbody-matriks-r');
    if(!tbodyX || !tbodyR) return;

    tbodyX.innerHTML = '';
    tbodyR.innerHTML = '';

    if(globalMatrixKeputusan.length === 0) {
        tbodyX.innerHTML = `<tr><td colspan="${kriteriaData.length + 1}" style="text-align:center;">Data kosong</td></tr>`;
        tbodyR.innerHTML = `<tr><td colspan="${kriteriaData.length + 1}" style="text-align:center;">Data kosong</td></tr>`;
        return;
    }

    globalMatrixKeputusan.forEach(row => {
        let tr = document.createElement('tr');
        let html = `<td><strong>${row.nama}</strong></td>`;
        kriteriaData.forEach(k => {
            html += `<td>${row.scores[k.kode] || 0}</td>`;
        });
        tr.innerHTML = html;
        tbodyX.appendChild(tr);
    });

    globalMatrixNormalisasi.forEach(row => {
        let tr = document.createElement('tr');
        let html = `<td><strong>${row.nama}</strong></td>`;
        kriteriaData.forEach(k => {
            html += `<td>${row.scores[k.kode] || 0}</td>`;
        });
        tr.innerHTML = html;
        tbodyR.appendChild(tr);
    });
}

// Print Logic
if (document.getElementById('btn-print')) {
    document.getElementById('btn-print').addEventListener('click', () => {
        const printData = globalRankedData.slice(0, currentKuota);
        let html = `
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>No KK</th>
                    <th>Nama Warga</th>
                    <th>Skor Akhir</th>
                    <th>Status Kelayakan</th>
                </tr>
            </thead>
            <tbody>
        `;
        printData.forEach((d, i) => {
            html += `
                <tr>
                    <td>#${i+1}</td>
                    <td>${d.nokk}</td>
                    <td><strong>${d.nama}</strong></td>
                    <td>${d.skorAkhir}</td>
                    <td>Lolos Penerima Bansos</td>
                </tr>
            `;
        });
        html += `</tbody></table>`;
        
        document.getElementById('print-table-wrapper').innerHTML = html;
        const now = new Date();
        document.getElementById('print-date').textContent = "Sukamaju, " + now.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
        window.print();
    });
}

// Start app
fetchData();

// Toggle Prioritas List
window.togglePrioritas = function() {
    showAllPrioritas = !showAllPrioritas;
    const btn = document.getElementById('toggle-prioritas-btn');
    if (btn) {
        btn.textContent = showAllPrioritas ? 'Tampilkan Sedikit' : 'Lihat Semua';
    }
    if (typeof globalRankedData !== 'undefined') {
        updateChartAndActivity(globalRankedData);
    }
};
