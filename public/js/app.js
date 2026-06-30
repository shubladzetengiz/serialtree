function openModal() {
    document.getElementById('modal').classList.add('show');
    document.getElementById('overlay').classList.add('show');
}

function closeModal() {
    document.getElementById('modal').classList.remove('show');
    document.getElementById('overlay').classList.remove('show');
    var usp = new URLSearchParams(location.search);
    if (!usp.has('edit')) {
        window.location.href = window.location.pathname + '?' + usp.toString();
        return;
    }
    usp.delete('edit');
    window.location.href = window.location.pathname + '?' + usp.toString();
}

function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
}

// --- View toggle (localStorage) ---
function setView(v) {
    var listEl = document.getElementById('list-view');
    var gridEl = document.getElementById('grid-view');
    var btnList = document.getElementById('view-list');
    var btnGrid = document.getElementById('view-grid');
    if (!listEl || !gridEl) return;
    if (v === 'list') {
        listEl.classList.remove('hidden');
        gridEl.classList.add('hidden');
        btnList.className = 'view-btn px-3 py-1.5 rounded-md text-sm font-medium transition bg-indigo-600 text-white';
        btnGrid.className = 'view-btn px-3 py-1.5 rounded-md text-sm font-medium transition text-gray-400 hover:text-gray-200';
    } else {
        gridEl.classList.remove('hidden');
        listEl.classList.add('hidden');
        btnGrid.className = 'view-btn px-3 py-1.5 rounded-md text-sm font-medium transition bg-indigo-600 text-white';
        btnList.className = 'view-btn px-3 py-1.5 rounded-md text-sm font-medium transition text-gray-400 hover:text-gray-200';
    }
    try { localStorage.setItem('serial2_view', v); } catch (e) {}
}

// --- Live search ---
var searchTimer;
$(document).on('input', 'input[name="search"]', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(liveSearch, 300);
});
$(document).on('change', 'select[name="status_filter"]', liveSearch);
$(document).on('change', 'select[name="sort"]', liveSearch);

function liveSearch() {
    var type = new URLSearchParams(location.search).get('type') || 'series';
    var params = { type: type, search: $('input[name="search"]').val() || '' };
    var sf = $('select[name="status_filter"]').val();
    if (sf) params.status_filter = sf;
    var so = $('select[name="sort"]').val();
    if (so && so !== 'date_desc') params.sort = so;

    history.replaceState(null, '', '?' + $.param(params));

    $.get(location.pathname, params, function (html) {
        var d = new DOMParser().parseFromString(html, 'text/html');
        var listNew = d.getElementById('list-view');
        var gridNew = d.getElementById('grid-view');
        var countNew = d.querySelector('.rounded-full.border');
        if (listNew) document.getElementById('list-view').innerHTML = listNew.innerHTML;
        if (gridNew) document.getElementById('grid-view').innerHTML = gridNew.innerHTML;
        if (countNew) {
            var c = document.querySelector('.rounded-full.border');
            if (c) c.textContent = countNew.textContent;
        }
        setView(savedView());
        $('.rating').each(function () {
            $(this).find('i.active').each(function () {
                $(this).addClass('fas').removeClass('far');
            });
        });
    });
}

// --- Rating stars (delegated, works after AJAX) ---
$(document).on('mouseenter', '.rating i', function () {
    var val = $(this).data('value');
    $(this).parent().find('i').each(function (i) {
        $(this).toggleClass('hovered', i < val);
    });
});
$(document).on('mouseleave', '.rating i', function () {
    $(this).parent().find('i').removeClass('hovered');
});
$(document).on('click', '.rating i', function () {
    var val = $(this).data('value');
    var container = $(this).parent();
    var type = container.data('type');
    var id = container.data('id');

    container.find('i').each(function (i) {
        $(this).toggleClass('fas', i < val).toggleClass('far', i >= val);
    });
    container.find('i.active').removeClass('active');
    container.find('i:lt(' + val + ')').addClass('active');

    var data = { update_rating: true, _csrf: csrf(), rating_value: val };
    data[type === 'movies' ? 'movie_id' : 'series_id'] = id;
    $.post(location.pathname + '?type=' + type, data, null, 'json');
});

// --- Status dropdown ---
let openDropdown = null;

function toggleDropdown(el) {
    var menu = el.parentElement.querySelector('.dropdown-menu');
    if (openDropdown && openDropdown !== menu) {
        openDropdown.classList.remove('show');
    }
    menu.classList.toggle('show');
    openDropdown = menu && menu.classList.contains('show') ? menu : null;
}

function statusClass(status) {
    return status === 'ნანახი' ? 'status-watched' : status === 'გასაგრძელებელია' ? 'status-ongoing' : 'status-towatch';
}

function updateStatus(id, status) {
    var c = document.querySelector('.status-container[data-id="' + id + '"]');
    var type = c ? c.getAttribute('data-type') : 'movies';
    var data = { update_status: true, _csrf: csrf(), status_value: status };
    data[type === 'movies' ? 'movie_id' : 'series_id'] = id;
    $.post(location.pathname + '?type=' + type, data, function (res) {
        if (res.success) {
            document.querySelectorAll('.status-container[data-id="' + id + '"]').forEach(function (c) {
                var badge = c.querySelector('.status-badge');
                badge.className = 'status-badge ' + statusClass(status);
                badge.innerHTML = __(status === 'ნანახი' ? 'status_watched' : status === 'გასაგრძელებელია' ? 'status_ongoing' : 'status_towatch') + ' <i class="fa-solid fa-caret-down text-xs"></i>';
            });
            if (openDropdown) { openDropdown.classList.remove('show'); openDropdown = null; }
        }
    }, 'json');
}

document.addEventListener('click', function (e) {
    if (openDropdown && !e.target.closest('.status-container')) {
        openDropdown.classList.remove('show');
        openDropdown = null;
    }
});

// --- Delete confirmation with timer ---
var deleteUrl = null;
var deleteTimer = null;
var deleteCount = 5;

function showDeleteModal(url) {
    deleteUrl = url;
    deleteCount = 5;
    document.getElementById('delete-countdown').textContent = deleteCount;
    document.getElementById('delete-confirm-btn').disabled = true;
    document.getElementById('delete-confirm-btn').className = 'bg-red-600 opacity-50 cursor-not-allowed text-white font-medium px-6 py-2 rounded-lg transition text-sm';
    document.getElementById('delete-modal').classList.add('show');
    document.getElementById('delete-overlay').classList.add('show');
    clearInterval(deleteTimer);
    deleteTimer = setInterval(function () {
        deleteCount--;
        document.getElementById('delete-countdown').textContent = deleteCount;
        if (deleteCount <= 0) {
            clearInterval(deleteTimer);
            var btn = document.getElementById('delete-confirm-btn');
            btn.disabled = false;
            btn.className = 'bg-red-600 hover:bg-red-500 text-white font-medium px-6 py-2 rounded-lg transition text-sm';
            btn.onclick = confirmDelete;
        }
    }, 1000);
}

function confirmDelete() {
    clearInterval(deleteTimer);
    document.getElementById('delete-modal').classList.remove('show');
    document.getElementById('delete-overlay').classList.remove('show');
    if (deleteUrl) window.location.href = deleteUrl;
}

function cancelDelete() {
    clearInterval(deleteTimer);
    document.getElementById('delete-modal').classList.remove('show');
    document.getElementById('delete-overlay').classList.remove('show');
    deleteUrl = null;
}

// --- Helpers ---
function savedView() {
    var v = 'grid';
    try { var s = localStorage.getItem('serial2_view'); if (s === 'list' || s === 'grid') v = s; } catch (e) {}
    return v;
}

// --- Modal: auto-set status based on season/episode fields ---
$(document).on('input', 'input[name="season"], input[name="episode"]', function () {
    var s = parseInt($('input[name="season"]').val()) || 0;
    var e = parseInt($('input[name="episode"]').val()) || 0;
    if (s > 99) {
        $('input[name="season"], input[name="episode"]').val('');
        $('select[name="status"]').val('სანახავია');
    } else if (s > 0 && e > 0) {
        $('select[name="status"]').val('ნანახი');
    } else if (s > 0) {
        $('select[name="status"]').val('გასაგრძელებელია');
    } else if (e > 0) {
        $('select[name="status"]').val('გასაგრძელებელია');
    } else {
        $('select[name="status"]').val('სანახავია');
    }
});

// --- Cover download ---
var coverTimer;
$(document).on('blur', 'input[name="cover"]', function () {
    clearTimeout(coverTimer);
    coverTimer = setTimeout(function () {
        var v = $('input[name="cover"]').val();
        if (v && !v.startsWith('uploads/')) downloadCover();
    }, 300);
});

function setCoverStatus(mode, msg) {
    var el = document.getElementById('cover-status');
    if (!el) return;
    el.textContent = msg;
    el.className = 'text-xs mt-1 ' + (mode === 'local' ? 'text-green-400' : 'text-gray-400');
}

function downloadCover() {
    var url = $('input[name="cover"]').val();
    if (!url || url.startsWith('uploads/')) return;
    var btn = $('button[onclick="downloadCover()"]');
    btn.html('<i class="fa-solid fa-spinner fa-spin"></i>');
    $.post(location.pathname + '?type=' + (new URLSearchParams(location.search).get('type') || 'series'), {
        download_cover: true, _csrf: csrf(), cover_url: url
    }, function (res) {
        btn.html('<i class="fa-solid fa-download"></i>');
        if (res.success) {
            $('input[name="cover"]').val(res.local_path);
            setCoverStatus('local', __('local_badge'));
        } else {
            setCoverStatus('online', __('online_badge'));
        }
    }, 'json').fail(function () {
        btn.html('<i class="fa-solid fa-download"></i>');
        setCoverStatus('online', __('online_badge'));
    });
}

// --- Init ---
document.addEventListener('DOMContentLoaded', function () {
    setView(savedView());
    $(document).find('.rating').each(function () {
        $(this).find('i.active').each(function () {
            $(this).addClass('fas').removeClass('far');
        });
    });
    var cv = $('input[name="cover"]').val();
    if (cv && cv.startsWith('uploads/')) {
        setCoverStatus('local', __('local_badge'));
    } else if (cv) {
        setCoverStatus('online', __('online_badge'));
    }
});
