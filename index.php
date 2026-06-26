<?php
// index.php - Digital Signage Player with Management Interface (Russian)

// Configuration
define('DATA_FILE', 'project.json');
define('UPLOAD_DIR', 'uploads/');

// Create uploads directory if it doesn't exist
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Load project data
function loadProject() {
    if (file_exists(DATA_FILE)) {
        $data = json_decode(file_get_contents(DATA_FILE), true);
        if ($data && isset($data['slides'])) {
            return $data;
        }
    }
    // Default project
    return [
        'slides' => [
            [
                'id' => 1,
                'type' => 'image',
                'content' => 'https://via.placeholder.com/1920x1080/3498db/ffffff?text=Слайд+1',
                'duration' => 5,
                'title' => 'Приветственный слайд'
            ],
            [
                'id' => 2,
                'type' => 'image',
                'content' => 'https://via.placeholder.com/1920x1080/e74c3c/ffffff?text=Слайд+2',
                'duration' => 5,
                'title' => 'Второй слайд'
            ]
        ],
        'current_index' => 0
    ];
}

// Save project data
function saveProject($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $project = loadProject();
    
    switch ($_POST['action']) {
        case 'get_project':
            echo json_encode($project);
            break;
            
        case 'update_slides':
            $slides = json_decode($_POST['slides'], true);
            $project['slides'] = $slides;
            saveProject($project);
            echo json_encode(['success' => true]);
            break;
            
        case 'update_duration':
            foreach ($project['slides'] as &$slide) {
                if ($slide['id'] == $_POST['slide_id']) {
                    $slide['duration'] = intval($_POST['duration']);
                    break;
                }
            }
            saveProject($project);
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_slide':
            $project['slides'] = array_values(array_filter($project['slides'], function($s) {
                return $s['id'] != $_POST['slide_id'];
            }));
            saveProject($project);
            echo json_encode(['success' => true]);
            break;
            
        case 'move_slide':
            $index = intval($_POST['index']);
            $direction = intval($_POST['direction']);
            $newIndex = $index + $direction;
            
            if ($newIndex >= 0 && $newIndex < count($project['slides'])) {
                $slide = $project['slides'][$index];
                array_splice($project['slides'], $index, 1);
                array_splice($project['slides'], $newIndex, 0, [$slide]);
                saveProject($project);
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'upload_file':
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $ext;
                $filepath = UPLOAD_DIR . $filename;
                
                if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
                    $type = 'image';
                    if (in_array(strtolower($ext), ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'])) {
                        $type = 'video';
                    }
                    
                    $slide = [
                        'id' => count($project['slides']) + 1,
                        'type' => $type,
                        'content' => $filepath,
                        'duration' => ($type === 'video') ? 10 : 5,
                        'title' => $_FILES['file']['name']
                    ];
                    
                    // Get video duration if it's a video (using getID3 or similar would be better)
                    if ($type === 'video') {
                        // Simple approach - set default, could use ffprobe if available
                        $slide['duration'] = 10;
                    }
                    
                    $project['slides'][] = $slide;
                    saveProject($project);
                    echo json_encode(['success' => true, 'slide' => $slide]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Файл не загружен']);
            }
            break;
            
        case 'add_iframe':
            $slide = [
                'id' => count($project['slides']) + 1,
                'type' => 'iframe',
                'content' => $_POST['url'],
                'duration' => intval($_POST['duration']),
                'title' => $_POST['title'] ?: $_POST['url']
            ];
            $project['slides'][] = $slide;
            saveProject($project);
            echo json_encode(['success' => true, 'slide' => $slide]);
            break;
            
        default:
            echo json_encode(['success' => false]);
    }
    exit;
}

// Main page
$project = loadProject();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Цифровой Стенд - Плеер</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * { margin: 0; padding: 0; }
        html, body { 
            width: 100%; 
            height: 100%; 
            overflow: hidden;
            background: #000;
        }
        #slideshow {
            width: 100vw;
            height: 100vh;
            position: relative;
            background: #000;
        }
        #slideshow .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: none;
            justify-content: center;
            align-items: center;
            background: #000;
        }
        #slideshow .slide.active {
            display: flex;
        }
        #slideshow .slide img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        #slideshow .slide iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        #slideshow .slide video {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        #management {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.92);
            z-index: 1000;
            display: none;
            overflow-y: auto;
            padding: 20px;
            color: #fff;
        }
        #management.active {
            display: block;
        }
        .slide-item {
            background: rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .slide-item:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.15);
        }
        .slide-preview {
            width: 120px;
            height: 80px;
            background: #222;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .slide-preview img, .slide-preview video {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        .slide-preview iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .slide-info {
            flex: 1;
            min-width: 0;
        }
        .slide-controls {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .duration-input {
            width: 60px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            border-radius: 4px;
            padding: 4px 8px;
        }
        .duration-input:focus {
            background: rgba(255,255,255,0.15);
            border-color: #0d6efd;
            outline: none;
        }
        #close-management {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
        }
        .upload-area {
            border: 2px dashed rgba(255,255,255,0.2);
            padding: 30px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: rgba(255,255,255,0.4);
            background: rgba(255,255,255,0.05);
        }
        .upload-area.dragover {
            border-color: #0d6efd;
            background: rgba(13,110,253,0.1);
        }
        .slide-type-badge {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .slide-type-image { background: #0d6efd; }
        .slide-type-video { background: #dc3545; }
        .slide-type-iframe { background: #198754; }
        .toast-container { z-index: 9999; }
        .admin-panel {
            background: rgba(0,0,0,0.6);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .admin-panel h3 {
            color: #fff;
            margin-bottom: 20px;
        }
        .slide-number {
            font-weight: bold;
            color: rgba(255,255,255,0.4);
            min-width: 30px;
            text-align: center;
            font-size: 14px;
        }
        .iframe-modal .modal-content {
            background: #1a1a1a;
            color: #fff;
        }
        .iframe-modal .modal-header {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .iframe-modal .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .iframe-modal .form-control {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
        }
        .iframe-modal .form-control:focus {
            background: rgba(255,255,255,0.15);
            border-color: #0d6efd;
            color: #fff;
        }
        .iframe-modal .form-label {
            color: rgba(255,255,255,0.7);
        }
        .btn-close-white {
            filter: invert(1);
        }
    </style>
</head>
<body>

<div id="slideshow">
    <?php foreach ($project['slides'] as $index => $slide): ?>
    <div class="slide" data-index="<?= $index ?>" data-id="<?= $slide['id'] ?>" data-type="<?= $slide['type'] ?>" data-duration="<?= $slide['duration'] ?>">
        <?php if ($slide['type'] === 'image'): ?>
            <img src="<?= htmlspecialchars($slide['content']) ?>" alt="Слайд">
        <?php elseif ($slide['type'] === 'video'): ?>
            <video src="<?= htmlspecialchars($slide['content']) ?>" muted></video>
        <?php elseif ($slide['type'] === 'iframe'): ?>
            <iframe src="<?= htmlspecialchars($slide['content']) ?>" allowfullscreen></iframe>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Management Interface -->
<div id="management">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <button id="close-management" class="btn btn-danger btn-lg">
                    <i class="bi bi-x-lg"></i> Закрыть
                </button>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="admin-panel">
                    <h3><i class="bi bi-sliders2"></i> Управление проектом</h3>
                    
                    <!-- Upload Area -->
                    <div id="upload-area" class="upload-area">
                        <i class="bi bi-cloud-upload" style="font-size: 48px;"></i>
                        <h5>Перетащите файлы сюда или нажмите для загрузки</h5>
                        <p class="text-muted">Поддерживаются изображения (jpg, png, gif), видео (mp4, webm) и iframe ссылки</p>
                        <input type="file" id="file-input" multiple accept="image/*,video/*" style="display:none">
                        <div class="mt-2">
                            <button class="btn btn-primary" id="upload-btn"><i class="bi bi-upload"></i> Загрузить файлы</button>
                            <button class="btn btn-success" id="add-iframe-btn" data-bs-toggle="modal" data-bs-target="#iframeModal">
                                <i class="bi bi-link-45deg"></i> Добавить iframe
                            </button>
                        </div>
                    </div>
                    
                    <!-- Slides List -->
                    <div id="slides-list">
                        <!-- Rendered by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Iframe Modal -->
<div class="modal fade iframe-modal" id="iframeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-link-45deg"></i> Добавить iframe</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="iframe-form">
                    <div class="mb-3">
                        <label class="form-label">URL страницы</label>
                        <input type="url" class="form-control" id="iframe-url" placeholder="https://example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Название (опционально)</label>
                        <input type="text" class="form-control" id="iframe-title" placeholder="Название слайда">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Длительность (секунд)</label>
                        <input type="number" class="form-control" id="iframe-duration" value="10" min="1" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="iframe-submit"><i class="bi bi-plus-lg"></i> Добавить</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentSlide = 0;
let slideTimer = null;
let isPlaying = true;
let project = <?= json_encode($project) ?>;
let iframeReloadTimers = {};

// Initialize
$(document).ready(function() {
    // Show first slide
    showSlide(0);
    
    // Start autoplay
    startSlideshow();
    
    // Double click to toggle management
    $(document).on('dblclick', function(e) {
        if (!e.target.closest('#management')) {
            toggleManagement();
        }
    });
    
    // Close management
    $('#close-management').on('click', function() {
        toggleManagement();
    });
    
    // Upload button
    $('#upload-btn').on('click', function() {
        $('#file-input').click();
    });
    
    // Iframe submit
    $('#iframe-submit').on('click', function() {
        const url = $('#iframe-url').val();
        const title = $('#iframe-title').val();
        const duration = $('#iframe-duration').val();
        
        if (!url) {
            alert('Пожалуйста, введите URL страницы');
            return;
        }
        
        addIframeSlide(url, title, duration);
        $('#iframeModal').modal('hide');
        $('#iframe-form')[0].reset();
        $('#iframe-duration').val(10);
    });
    
    // File input change
    $('#file-input').on('change', function(e) {
        const files = e.target.files;
        for (let file of files) {
            uploadFile(file);
        }
        this.value = '';
    });
    
    // Drag and drop
    const uploadArea = document.getElementById('upload-area');
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    uploadArea.addEventListener('dragleave', function(e) {
        this.classList.remove('dragover');
    });
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        const files = e.dataTransfer.files;
        for (let file of files) {
            uploadFile(file);
        }
    });
    
    // Load slides
    loadSlides();
    
    // Bootstrap modal
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js';
    document.head.appendChild(script);
});

function showSlide(index) {
    const slides = $('#slideshow .slide');
    if (!slides.length) return;
    
    if (index >= slides.length) index = 0;
    if (index < 0) index = slides.length - 1;
    
    slides.removeClass('active');
    const currentSlideEl = $(slides[index]);
    currentSlideEl.addClass('active');
    
    // Handle iframe reload
    const iframe = currentSlideEl.find('iframe')[0];
    if (iframe) {
        // Reload iframe
        const src = iframe.src;
        iframe.src = '';
        setTimeout(() => {
            iframe.src = src;
        }, 100);
    }
    
    // Handle video playback
    const video = currentSlideEl.find('video')[0];
    if (video) {
        video.currentTime = 0;
        video.play().catch(() => {});
    }
    
    currentSlide = index;
    
    // Restart timer with new duration
    if (isPlaying) {
        startSlideshow();
    }
}

function startSlideshow() {
    stopSlideshow();
    const duration = getCurrentDuration();
    if (duration > 0) {
        slideTimer = setTimeout(function() {
            if (isPlaying) {
                const slides = $('#slideshow .slide');
                let nextIndex = (currentSlide + 1) % slides.length;
                showSlide(nextIndex);
            }
        }, duration);
    }
}

function stopSlideshow() {
    if (slideTimer) {
        clearTimeout(slideTimer);
        slideTimer = null;
    }
}

function getCurrentDuration() {
    const activeSlide = $('#slideshow .slide.active');
    if (activeSlide.length) {
        const duration = parseInt(activeSlide.data('duration'));
        return duration * 1000;
    }
    return 5000;
}

function toggleManagement() {
    isPlaying = !isPlaying;
    if (isPlaying) {
        $('#management').removeClass('active');
        // Resume video if any
        const video = $('#slideshow .slide.active video')[0];
        if (video) video.play();
        startSlideshow();
    } else {
        $('#management').addClass('active');
        stopSlideshow();
        // Pause video if any
        const video = $('#slideshow .slide.active video')[0];
        if (video) video.pause();
        loadSlides();
    }
}

function loadSlides() {
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: { action: 'get_project' },
        dataType: 'json',
        success: function(data) {
            if (data && data.slides) {
                project = data;
                renderSlides();
                updateSlideshow();
            }
        }
    });
}

function renderSlides() {
    const container = $('#slides-list');
    let html = '<h4 class="mb-3">Слайды (' + project.slides.length + ')</h4>';
    
    if (!project.slides.length) {
        html += '<div class="alert alert-info">Слайды не добавлены. Загрузите файлы или добавьте iframe выше.</div>';
        container.html(html);
        return;
    }
    
    project.slides.forEach(function(slide, index) {
        const typeClass = 'slide-type-' + slide.type;
        const typeLabel = {
            'image': 'Изображение',
            'video': 'Видео',
            'iframe': 'Веб-страница'
        }[slide.type] || slide.type.toUpperCase();
        
        let preview = '';
        if (slide.type === 'iframe') {
            preview = '<iframe src="' + slide.content + '" style="width:100%;height:100%;border:none;"></iframe>';
        } else if (slide.type === 'video') {
            preview = '<video src="' + slide.content + '" style="width:100%;height:100%;object-fit:cover;"></video>';
        } else {
            preview = '<img src="' + slide.content + '" style="width:100%;height:100%;object-fit:cover;">';
        }
        
        html += `
            <div class="slide-item" data-id="${slide.id}">
                <div class="slide-number">#${index + 1}</div>
                <div class="slide-preview">${preview}</div>
                <div class="slide-info">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="slide-type-badge ${typeClass}">${typeLabel}</span>
                        <span class="text-truncate" style="max-width:200px;">${slide.title || slide.content}</span>
                    </div>
                    <div class="mt-1 d-flex align-items-center gap-2">
                        <label class="text-muted small">Длительность:</label>
                        <input type="number" class="duration-input" value="${slide.duration}" min="1" 
                               data-id="${slide.id}" style="width:60px;">
                        <span class="text-muted small">сек.</span>
                    </div>
                </div>
                <div class="slide-controls">
                    <button class="btn btn-sm btn-outline-primary move-up" data-id="${slide.id}" data-dir="-1" ${index === 0 ? 'disabled' : ''}>
                        <i class="bi bi-arrow-up"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-primary move-down" data-id="${slide.id}" data-dir="1" ${index === project.slides.length - 1 ? 'disabled' : ''}>
                        <i class="bi bi-arrow-down"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger delete-slide" data-id="${slide.id}">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    container.html(html);
    
    // Event handlers
    $('.duration-input').on('change', function() {
        const id = $(this).data('id');
        const duration = $(this).val();
        if (duration > 0) {
            updateDuration(id, duration);
        } else {
            $(this).val(1);
            updateDuration(id, 1);
        }
    });
    
    $('.delete-slide').on('click', function() {
        const id = $(this).data('id');
        if (confirm('Удалить этот слайд?')) {
            deleteSlide(id);
        }
    });
    
    $('.move-up, .move-down').on('click', function() {
        const id = $(this).data('id');
        const dir = parseInt($(this).data('dir'));
        const index = project.slides.findIndex(s => s.id == id);
        if (index !== -1) {
            moveSlide(index, dir);
        }
    });
}

function updateSlideshow() {
    // Remove old slides
    $('#slideshow .slide').remove();
    
    // Add new slides
    project.slides.forEach(function(slide, index) {
        let content = '';
        if (slide.type === 'image') {
            content = `<img src="${slide.content}" alt="Слайд">`;
        } else if (slide.type === 'video') {
            content = `<video src="${slide.content}" muted></video>`;
        } else if (slide.type === 'iframe') {
            content = `<iframe src="${slide.content}" allowfullscreen></iframe>`;
        }
        
        $('#slideshow').append(`
            <div class="slide" data-index="${index}" data-id="${slide.id}" data-type="${slide.type}" data-duration="${slide.duration}">
                ${content}
            </div>
        `);
    });
    
    // Show first slide
    showSlide(0);
}

function updateDuration(id, duration) {
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: { 
            action: 'update_duration',
            slide_id: id,
            duration: duration
        },
        dataType: 'json',
        success: function() {
            loadSlides();
            showToast('Длительность обновлена');
        }
    });
}

function deleteSlide(id) {
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: {
            action: 'delete_slide',
            slide_id: id
        },
        dataType: 'json',
        success: function() {
            loadSlides();
            showToast('Слайд удален');
        }
    });
}

function moveSlide(index, direction) {
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: {
            action: 'move_slide',
            index: index,
            direction: direction
        },
        dataType: 'json',
        success: function() {
            loadSlides();
            showToast('Слайд перемещен');
        }
    });
}

function uploadFile(file) {
    const formData = new FormData();
    formData.append('action', 'upload_file');
    formData.append('file', file);
    
    // Show loading toast
    showToast('Загрузка: ' + file.name + '...', 'info');
    
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadSlides();
                showToast('Файл загружен: ' + file.name);
            } else {
                showToast('Ошибка загрузки: ' + (response.error || 'Неизвестная ошибка'), 'danger');
            }
        },
        error: function() {
            showToast('Ошибка загрузки файла', 'danger');
        }
    });
}

function addIframeSlide(url, title, duration) {
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: {
            action: 'add_iframe',
            url: url,
            title: title || url,
            duration: duration || 10
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadSlides();
                showToast('Iframe добавлен');
            } else {
                showToast('Ошибка добавления iframe', 'danger');
            }
        },
        error: function() {
            showToast('Ошибка добавления iframe', 'danger');
        }
    });
}

function showToast(message, type = 'success') {
    const bgColor = {
        'success': 'bg-success',
        'danger': 'bg-danger',
        'info': 'bg-info',
        'warning': 'bg-warning'
    }[type] || 'bg-success';
    
    const toast = $(`
        <div class="toast align-items-center text-white ${bgColor} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);
    
    const container = $('.toast-container');
    if (!container.length) {
        $('body').append('<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>');
    }
    
    $('.toast-container').append(toast);
    const bsToast = new bootstrap.Toast(toast[0], { autohide: true, delay: 3000 });
    bsToast.show();
    toast.on('hidden.bs.toast', function() { $(this).remove(); });
}
</script>

</body>
</html>
