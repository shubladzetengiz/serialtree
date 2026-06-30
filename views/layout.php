<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> — <?= __('app_name') ?></title>
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .aspect-9-16 { aspect-ratio: 9/16; }
        .aspect-2-3 { aspect-ratio: 2/3; }
        .star { color: #4b5563; cursor: pointer; transition: color .15s; }
        .star.active,
        .star.hovered { color: #f59e0b; }
        .status-badge { cursor: pointer; border-radius: 9999px; padding: .125rem .75rem; font-size: .875rem; display: inline-flex; align-items: center; gap: .25rem; }
        .status-watched { background: #064e3b; color: #6ee7b7; }
        .status-towatch { background: #450a0a; color: #fca5a5; }
        .status-ongoing { background: #172554; color: #93c5fd; }
        .dropdown-menu { display: none; position: absolute; z-index: 50; background: #1f2937; border: 1px solid #374151; border-radius: .5rem; box-shadow: 0 4px 12px rgba(0,0,0,.4); padding: .25rem 0; min-width: 130px; }
        .dropdown-menu.show { display: block; }
        .dropdown-menu li { padding: .375rem 1rem; cursor: pointer; transition: background .1s; color: #d1d5db; }
        .dropdown-menu li:hover { background: #374151; color: #fff; }
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 40; }
        .modal { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%); z-index: 50; background: #1f2937; border: 1px solid #374151; border-radius: 1rem; box-shadow: 0 20px 60px rgba(0,0,0,.5); width: 95%; max-width: 520px; max-height: 90vh; overflow-y: auto; padding: 1rem; }
        @media (min-width: 640px) { .modal { padding: 1.5rem; } }
        .modal.show { display: block; }
        .overlay.show { display: block; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #111827; }
        ::-webkit-scrollbar-thumb { background: #374151; border-radius: 4px; }
        .grid-card { transition: transform .2s, box-shadow .2s; }
        .grid-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,.5); }
        .grid-card .overlay { display: none; }
        .grid-card:hover .overlay { display: flex; }
        .view-btn { cursor: pointer; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        #scroll-top { transition: opacity .3s, visibility .3s; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 antialiased min-h-screen">
    <nav class="bg-gray-800 shadow border-b border-gray-700 sticky top-0 z-30">
        <div class="max-w-6xl mx-auto px-2 sm:px-4 flex items-center justify-between h-14">
            <a href="?type=series" class="text-base sm:text-lg font-bold text-indigo-400 flex items-center gap-1 sm:gap-2 shrink-0">
                <i class="fa-solid fa-film"></i><span class="hidden sm:inline">SerialManager</span>
            </a>
            <div class="flex items-center gap-1 sm:gap-2 overflow-x-auto no-scrollbar">
                <div class="flex gap-0.5 sm:gap-1">
                    <a href="?type=series" class="px-2 sm:px-4 py-2 rounded-lg text-xs sm:text-sm font-medium whitespace-nowrap transition <?= $type === 'series' ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?>">
                        <i class="fa-solid fa-tv mr-0.5 sm:mr-1"></i><?= __('series') ?>
                    </a>
                    <a href="?type=movies" class="px-2 sm:px-4 py-2 rounded-lg text-xs sm:text-sm font-medium whitespace-nowrap transition <?= $type === 'movies' ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?>">
                        <i class="fa-solid fa-video mr-0.5 sm:mr-1"></i><?= __('movies') ?>
                    </a>
                </div>
                <?= lang_switcher() ?>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        <!-- toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4 sm:mb-6">
            <div class="flex items-center gap-2 bg-gray-800 rounded-lg border border-gray-700 p-1">
                <button onclick="setView('grid')" id="view-grid" class="view-btn px-2 sm:px-3 py-1.5 rounded-md text-xs sm:text-sm font-medium transition text-gray-400 hover:text-gray-200">
                    <i class="fa-solid fa-th-large mr-0.5 sm:mr-1"></i><?= __('grid') ?>
                </button>
                <button onclick="setView('list')" id="view-list" class="view-btn px-2 sm:px-3 py-1.5 rounded-md text-xs sm:text-sm font-medium transition text-gray-400 hover:text-gray-200">
                    <i class="fa-solid fa-list mr-0.5 sm:mr-1"></i><?= __('list') ?>
                </button>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <span class="text-xs sm:text-sm text-gray-400 bg-gray-800 px-2 sm:px-3 py-1 rounded-full border border-gray-700 whitespace-nowrap">
                    <?= __('record_count', $total) ?>
                </span>
                <button onclick="openModal()" class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs sm:text-sm font-medium px-3 sm:px-4 py-2 rounded-lg transition flex items-center gap-1 sm:gap-2 whitespace-nowrap">
                    <i class="fa-solid fa-plus"></i> <?= __('add') ?>
                </button>
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>"
                   class="bg-gray-700 hover:bg-gray-600 text-gray-200 text-xs sm:text-sm px-2 sm:px-3 py-2 rounded-lg transition flex items-center gap-1 sm:gap-2 whitespace-nowrap">
                    <i class="fa-solid fa-download"></i><span class="hidden sm:inline"><?= __('export') ?></span>
                </a>
                <button onclick="openImportModal()"
                        class="bg-gray-700 hover:bg-gray-600 text-gray-200 text-xs sm:text-sm px-2 sm:px-3 py-2 rounded-lg transition flex items-center gap-1 sm:gap-2 whitespace-nowrap">
                    <i class="fa-solid fa-upload"></i><span class="hidden sm:inline"><?= __('import') ?></span>
                </button>
            </div>
        </div>

        <?php if ($flash): ?>
        <div class="mb-4 px-4 py-2 rounded-lg text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300' ?> flex items-center gap-2">
            <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= h($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Search / Filter -->
        <form method="GET" class="flex flex-wrap gap-3 mb-6">
            <input type="hidden" name="type" value="<?= $type ?>">
            <div class="flex-1 min-w-[200px]">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>
                    <input type="text" name="search" value="<?= h($search) ?>" placeholder="<?= __('search_placeholder') ?>"
                           class="w-full pl-10 pr-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-100 placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
            </div>
                <select name="status_filter"
                        class="px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value=""><?= __('all_statuses') ?></option>
                    <option value="ნანახი" <?= $statusFilter === 'ნანახი' ? 'selected' : '' ?>><?= __('status_watched') ?></option>
                    <option value="გასაგრძელებელია" <?= $statusFilter === 'გასაგრძელებელია' ? 'selected' : '' ?>><?= __('status_ongoing') ?></option>
                    <option value="სანახავია" <?= $statusFilter === 'სანახავია' ? 'selected' : '' ?>><?= __('status_towatch') ?></option>
                </select>
                <select name="sort"
                        class="px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>><?= __('sort_newest') ?></option>
                    <option value="date_asc"  <?= $sort === 'date_asc'  ? 'selected' : '' ?>><?= __('sort_oldest') ?></option>
                    <option value="alpha_asc" <?= $sort === 'alpha_asc' ? 'selected' : '' ?>><?= __('sort_az') ?></option>
                    <option value="alpha_desc"<?= $sort === 'alpha_desc'? 'selected' : '' ?>><?= __('sort_za') ?></option>
                </select>
        </form>

        <!-- ============ LIST VIEW ============ -->
        <div id="list-view" class="hidden">
            <!-- Desktop table -->
            <div class="hidden md:block overflow-hidden bg-gray-800 rounded-xl shadow border border-gray-700">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-800 border-b border-gray-700 text-left text-gray-400 font-medium">
                            <th class="p-3 w-28"><?= __('th_cover') ?></th>
                            <th class="p-3"><?= __('th_title') ?></th>
                            <?php if ($type === 'series'): ?>
                                <th class="p-3"><?= __('th_season') ?></th>
                                <th class="p-3"><?= __('th_episode') ?></th>
                            <?php else: ?>
                                <th class="p-3"><?= __('th_description') ?></th>
                            <?php endif; ?>
                            <th class="p-3"><?= __('th_status') ?></th>
                            <th class="p-3 text-center"><?= __('th_rating') ?></th>
                            <th class="p-3 text-right"><?= __('th_action') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $row): ?>
                    <tr class="border-b border-gray-700 hover:bg-gray-700 transition">
                            <td class="p-3">
                                <img src="<?= h($row['cover']) ?>" alt="cover"
                                     class="w-20 rounded-lg object-cover <?= $type === 'series' ? 'aspect-9-16' : 'aspect-2-3' ?>">
                            </td>
                            <td class="p-3 font-medium text-gray-100"><?= h($row['title']) ?></td>
                            <?php if ($type === 'series'): ?>
                                <td class="p-3 text-gray-400"><?= h($row['season']) ?></td>
                                <td class="p-3 text-gray-400"><?= h($row['episode']) ?></td>
                            <?php else: ?>
                                <td class="p-3 text-gray-400 max-w-[200px] truncate"><?= h($row['description']) ?></td>
                            <?php endif; ?>
                            <td class="p-3">
                                <div class="relative inline-block status-container" data-type="<?= $type ?>" data-id="<?= $row['id'] ?>">
                                    <span class="status-badge <?= $row['status'] === 'ნანახი' ? 'status-watched' : ($row['status'] === 'გასაგრძელებელია' ? 'status-ongoing' : 'status-towatch') ?>" onclick="toggleDropdown(this)">
                                        <?= __status($row['status']) ?> <i class="fa-solid fa-caret-down text-xs"></i>
                                    </span>
                                    <ul class="dropdown-menu">
                                        <li onclick="updateStatus(<?= $row['id'] ?>, 'ნანახი')"><?= __('status_watched') ?></li>
                                        <li onclick="updateStatus(<?= $row['id'] ?>, 'გასაგრძელებელია')"><?= __('status_ongoing') ?></li>
                                        <li onclick="updateStatus(<?= $row['id'] ?>, 'სანახავია')"><?= __('status_towatch') ?></li>
                                    </ul>
                                </div>
                            </td>
                            <td class="p-3 text-center">
                                <div class="rating inline-flex gap-0.5" data-type="<?= $type ?>" data-id="<?= $row['id'] ?>">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa<?= $i <= $row['rating'] ? 's' : 'r' ?> fa-star star <?= $i <= $row['rating'] ? 'active' : '' ?>" data-value="<?= $i ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td class="p-3 text-right">
                                <?php if (!empty($row['resource_url'])): ?>
                                    <a href="<?= h($row['resource_url']) ?>" target="_blank" class="text-green-400 hover:text-green-300 px-1"><i class="fa-solid fa-globe"></i></a>
                                <?php endif; ?>
                                <a href="?type=<?= $type ?>&edit=<?= $row['id'] ?>" class="text-indigo-400 hover:text-indigo-300 px-2"><i class="fa-solid fa-pen"></i></a>
                                <a href="?type=<?= $type ?>&delete=<?= $row['id'] ?>" class="text-red-400 hover:text-red-300 px-2" onclick="event.preventDefault(); showDeleteModal(this.href)"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile horizontal cards -->
        <div class="block md:hidden">
            <?php foreach ($items as $row): ?>
                <div class="bg-gray-800 rounded-xl shadow border border-gray-700 overflow-hidden mb-3 sm:mb-4 flex">
                    <img src="<?= h($row['cover']) ?>" alt="cover"
                         class="w-20 sm:w-24 object-cover <?= $type === 'series' ? 'aspect-9-16' : 'aspect-2-3' ?>">
                    <div class="flex-1 p-3 flex flex-col justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-100"><?= h($row['title']) ?></h3>
                            <?php if ($type === 'series'): ?>
                                <p class="text-xs text-gray-400"><?= __('label_season') ?> <?= h($row['season'] ?? '—') ?>, <?= __('label_episode') ?> <?= h($row['episode'] ?? '—') ?></p>
                            <?php else: ?>
                                <p class="text-xs text-gray-400 line-clamp-2"><?= h($row['description']) ?></p>
                            <?php endif; ?>
                            <div class="mt-2">
                                <div class="relative inline-block status-container" data-type="<?= $type ?>" data-id="<?= $row['id'] ?>">
                                    <span class="status-badge text-xs <?= $row['status'] === 'ნანახი' ? 'status-watched' : ($row['status'] === 'გასაგრძელებელია' ? 'status-ongoing' : 'status-towatch') ?>" onclick="toggleDropdown(this)">
                                        <?= __status($row['status']) ?> <i class="fa-solid fa-caret-down text-xs"></i>
                                    </span>
                                    <ul class="dropdown-menu">
                                        <li onclick="updateStatus(<?= $row['id'] ?>, 'ნანახი')"><?= __('status_watched') ?></li>
                                        <li onclick="updateStatus(<?= $row['id'] ?>, 'გასაგრძელებელია')"><?= __('status_ongoing') ?></li>
                                        <li onclick="updateStatus(<?= $row['id'] ?>, 'სანახავია')"><?= __('status_towatch') ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between mt-2">
                            <div class="rating inline-flex gap-0.5" data-type="<?= $type ?>" data-id="<?= $row['id'] ?>">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa<?= $i <= $row['rating'] ? 's' : 'r' ?> fa-star star <?= $i <= $row['rating'] ? 'active' : '' ?> text-sm" data-value="<?= $i ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="flex gap-2">
                                <?php if (!empty($row['resource_url'])): ?>
                                    <a href="<?= h($row['resource_url']) ?>" target="_blank" class="text-green-400 text-sm hover:text-green-300"><i class="fa-solid fa-globe"></i></a>
                                <?php endif; ?>
                                <a href="?type=<?= $type ?>&edit=<?= $row['id'] ?>" class="text-indigo-400 text-sm hover:text-indigo-300"><i class="fa-solid fa-pen"></i></a>
                                <a href="?type=<?= $type ?>&delete=<?= $row['id'] ?>" class="text-red-400 text-sm hover:text-red-300" onclick="event.preventDefault(); showDeleteModal(this.href)"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ============ GRID VIEW (кино-сайт стиль) ============ -->
        <div id="grid-view">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                <?php foreach ($items as $row): ?>
                <div class="grid-card bg-gray-800 rounded-xl shadow border border-gray-700 overflow-hidden relative">
                    <div class="relative">
                        <img src="<?= h($row['cover']) ?>" alt="cover"
                             class="w-full object-cover <?= $type === 'series' ? 'aspect-9-16' : 'aspect-2-3' ?>">
                        <!-- hover overlay -->
                        <div class="overlay absolute inset-0 bg-black/70 flex-col items-center justify-center gap-2 p-3">
                            <div class="rating inline-flex gap-0.5 mb-2" data-type="<?= $type ?>" data-id="<?= $row['id'] ?>">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa<?= $i <= $row['rating'] ? 's' : 'r' ?> fa-star star <?= $i <= $row['rating'] ? 'active' : '' ?> text-lg" data-value="<?= $i ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="flex gap-2 mt-1">
                                <?php if (!empty($row['resource_url'])): ?>
                                    <a href="<?= h($row['resource_url']) ?>" target="_blank" class="text-green-400 hover:text-green-300 text-lg"><i class="fa-solid fa-globe"></i></a>
                                <?php endif; ?>
                                <a href="?type=<?= $type ?>&edit=<?= $row['id'] ?>" class="text-indigo-400 hover:text-indigo-300 text-lg"><i class="fa-solid fa-pen"></i></a>
                                <a href="?type=<?= $type ?>&delete=<?= $row['id'] ?>" class="text-red-400 hover:text-red-300 text-lg" onclick="event.preventDefault(); showDeleteModal(this.href)"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </div>
                        <!-- status badge on poster -->
                            <div class="absolute top-2 left-2">
                                <span class="status-badge text-xs <?= $row['status'] === 'ნანახი' ? 'status-watched' : ($row['status'] === 'გასაგრძელებელია' ? 'status-ongoing' : 'status-towatch') ?>">
                                    <?= __status($row['status']) ?>
                                </span>
                            </div>
                        <!-- rating badge top-right -->
                        <?php if ($row['rating'] > 0): ?>
                            <div class="absolute top-2 right-2 bg-black/60 rounded-lg px-2 py-0.5 text-xs font-bold text-yellow-400">
                                ★ <?= $row['rating'] ?>/5
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-2.5">
                        <h3 class="text-sm font-semibold text-gray-100 truncate" title="<?= h($row['title']) ?>"><?= h($row['title']) ?></h3>
                        <?php if ($type === 'series'): ?>
                            <p class="text-xs text-gray-500 mt-0.5">S<?= h($row['season'] ?? '—') ?> E<?= h($row['episode'] ?? '—') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <!-- Modal overlay -->
    <div id="overlay" class="overlay" onclick="closeModal()"></div>
    <!-- Modal -->
    <div id="modal" class="modal">
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="id" value="<?= h($editData['id'] ?? '') ?>">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-100"><?= $isEditing ? __('edit') : __('add') ?></h2>
                <button type="button" onclick="closeModal()" class="text-gray-500 hover:text-gray-300 text-xl">&times;</button>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('label_cover') ?></label>
                    <div class="flex gap-2">
                        <input type="text" name="cover" id="cover-input" value="<?= h($editData['cover'] ?? '') ?>" required
                               class="flex-1 px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-sm text-gray-100 placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <button type="button" onclick="downloadCover()"
                                class="bg-gray-600 hover:bg-gray-500 text-white text-xs px-3 rounded-lg transition flex items-center gap-1">
                            <i class="fa-solid fa-download"></i>
                        </button>
                    </div>
                    <span id="cover-status" class="text-xs mt-1"></span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('label_title') ?></label>
                    <input type="text" name="title" value="<?= h($editData['title'] ?? '') ?>" required
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-sm text-gray-100 placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <?php if ($type === 'series'): ?>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('label_season') ?> <span class="text-gray-500"><?= __('optional') ?></span></label>
                        <input type="number" name="season" min="1" value="<?= h($editData['season'] ?? '') ?>"
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('label_episode') ?> <span class="text-gray-500"><?= __('optional') ?></span></label>
                        <input type="number" name="episode" min="1" value="<?= h($editData['episode'] ?? '') ?>"
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                </div>
                <?php else: ?>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('label_description') ?></label>
                    <textarea name="description" rows="3"
                              class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-sm text-gray-100 placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 outline-none"><?= h($editData['description'] ?? '') ?></textarea>
                </div>
                <?php endif; ?>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('label_status') ?></label>
                    <select name="status"
                            class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="სანახავია" <?= ($editData['status'] ?? 'სანახავია') === 'სანახავია' ? 'selected' : '' ?>><?= __('status_towatch') ?></option>
                        <option value="ნანახი" <?= ($editData['status'] ?? '') === 'ნანახი' ? 'selected' : '' ?>><?= __('status_watched') ?></option>
                        <option value="გასაგრძელებელია" <?= ($editData['status'] ?? '') === 'გასაგრძელებელია' ? 'selected' : '' ?>><?= __('status_ongoing') ?></option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('label_source') ?></label>
                    <input type="url" name="resource_url" value="<?= h($editData['resource_url'] ?? '') ?>"
                           placeholder="https://..."
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-sm text-gray-100 placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1"><?= __('label_rating') ?></label>
                    <input type="number" name="rating" min="0" max="5" value="<?= h($editData['rating'] ?? 0) ?>" required
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-sm text-gray-100 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>
            <div class="flex gap-3 mt-5">
                <button type="submit" name="<?= $isEditing ? 'edit' : 'add' ?>"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-medium px-4 py-2.5 rounded-lg transition text-sm">
                    <i class="fa-solid fa-check mr-1"></i> <?= $isEditing ? __('save') : __('add') ?>
                </button>
                <button type="button" onclick="closeModal()"
                        class="px-4 py-2.5 border border-gray-600 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-700 transition">
                    <?= __('cancel') ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Delete confirmation overlay -->
    <div id="delete-overlay" class="overlay" onclick="cancelDelete()"></div>
    <!-- Delete confirmation modal -->
    <div id="delete-modal" class="modal">
        <div class="text-center">
            <div class="text-2xl mb-2"><i class="fa-solid fa-trash text-red-400"></i></div>
            <h2 class="text-lg font-bold text-gray-100 mb-1"><?= __('delete_title') ?></h2>
            <p class="text-sm text-gray-400 mb-4"><?= __('delete_confirm_text', '<span id="delete-countdown" class="text-indigo-400 font-bold">5</span>') ?></p>
            <div class="flex gap-3 justify-center">
                <button id="delete-confirm-btn" disabled
                        class="bg-red-600 opacity-50 cursor-not-allowed text-white font-medium px-6 py-2 rounded-lg transition text-sm">
                    <i class="fa-solid fa-check mr-1"></i> <?= __('confirm') ?>
                </button>
                <button onclick="cancelDelete()"
                        class="px-6 py-2 border border-gray-600 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-700 transition">
                    <?= __('cancel') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Import modal overlay -->
    <div id="import-overlay" class="overlay" onclick="closeImportModal()"></div>
    <!-- Import modal -->
    <div id="import-modal" class="modal">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-100"><?= __('import') ?></h2>
                <button type="button" onclick="closeImportModal()" class="text-gray-500 hover:text-gray-300 text-xl">&times;</button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2"><?= __('import_file') ?></label>
                    <input type="file" name="sql_file" accept=".sql" required
                           class="w-full text-sm text-gray-300 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-600 file:text-white hover:file:bg-indigo-500 cursor-pointer">
                </div>
                <p class="text-xs text-gray-500"><?= __('import_hint') ?></p>
            </div>
            <div class="flex gap-3 mt-5">
                <button type="submit" name="import"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-medium px-4 py-2.5 rounded-lg transition text-sm">
                    <i class="fa-solid fa-upload mr-1"></i> <?= __('import') ?>
                </button>
                <button type="button" onclick="closeImportModal()"
                        class="px-4 py-2.5 border border-gray-600 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-700 transition">
                    <?= __('cancel') ?>
                </button>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>var _t = <?= json_encode($lang) ?>; function __(k) { return _t[k] || k; }
    function openImportModal() { document.getElementById('import-modal').classList.add('show'); document.getElementById('import-overlay').classList.add('show'); }
    function closeImportModal() { document.getElementById('import-modal').classList.remove('show'); document.getElementById('import-overlay').classList.remove('show'); }
    </script>
    <script src="public/js/app.js"></script>
    <?php if ($isEditing): ?>
    <script>document.addEventListener('DOMContentLoaded', openModal);</script>
    <?php endif; ?>

    <button id="scroll-top" onclick="window.scrollTo({top:0,behavior:'smooth'})"
            class="fixed bottom-4 right-4 z-50 w-10 h-10 rounded-full bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg flex items-center justify-center opacity-0 invisible">
        <i class="fa-solid fa-arrow-up"></i>
    </button>
    <script>
    document.addEventListener('scroll', function () {
        var btn = document.getElementById('scroll-top');
        if (window.scrollY > 300) {
            btn.classList.remove('opacity-0', 'invisible');
        } else {
            btn.classList.add('opacity-0', 'invisible');
        }
    });
    </script>
</body>
</html>
