<?php
session_start();

// 统一上传大小限制（单文件）：集中于此函数，前后端共用
function getUploadMaxBytes() {
    return 100 * 1024 * 1024; // 100MB
}

// 创建上传目录
$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 允许的文件类型
$allowedTypes = [
    // 图片文件
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml',
    // 视频文件
    'video/mp4', 'video/avi', 'video/mov', 'video/wmv', 'video/flv', 'video/webm', 'video/mkv',
    // 文档文件
    'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    // 文本文件
    'text/plain', 'text/csv', 'text/html', 'text/css', 'text/javascript', 'application/json',
    // 压缩包文件
    'application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/vnd.rar', 'application/x-7z-compressed', 'application/x-tar', 'application/gzip', 'application/x-gzip', 'application/x-bzip2', 'application/x-xz', 'application/x-gtar', 'application/x-tgz'
];

$allowedExtensions = [
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg',
    'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv',
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'txt', 'csv', 'html', 'css', 'js', 'json',
    'zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2', 'tbz2', 'xz'
];

// 处理文件上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_POST['action'] === 'upload' && isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileTmpName = $file['tmp_name'];
        $fileType = $file['type'];
        $fileError = $file['error'];
        
        // 检查文件错误
        if ($fileError !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => '文件上传失败']);
            exit;
        }
        
        // 检查文件大小（统一限制）
        if ($fileSize > getUploadMaxBytes()) {
            $limitMb = number_format(getUploadMaxBytes() / 1024 / 1024, 0);
            echo json_encode(['success' => false, 'message' => '文件大小不能超过' . $limitMb . 'MB']);
            exit;
        }
        
        // 获取文件扩展名
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // 检查文件类型和扩展名：
        // 1) 扩展名必须在白名单内；
        // 2) MIME 在白名单内，或为通用 'application/octet-stream'（部分浏览器/系统对压缩包/二进制给此类型），或为空
        if (!in_array($fileExtension, $allowedExtensions) || !($fileType && in_array($fileType, $allowedTypes) || $fileType === 'application/octet-stream' || $fileType === '' || $fileType === null)) {
            echo json_encode(['success' => false, 'message' => '不支持的文件类型']);
            exit;
        }
        
        // 使用原始文件名，规范化为UTF-8并清理非法字符
        $baseOriginalName = pathinfo(basename($fileName), PATHINFO_FILENAME);
        $detectedEncoding = function_exists('mb_detect_encoding') ? mb_detect_encoding($baseOriginalName, ['UTF-8', 'GBK', 'GB2312', 'BIG5', 'ISO-8859-1'], true) : false;
        if ($detectedEncoding && $detectedEncoding !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $baseOriginalName = mb_convert_encoding($baseOriginalName, 'UTF-8', $detectedEncoding);
        } elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $baseOriginalName);
            if ($converted !== false) {
                $baseOriginalName = $converted;
            }
        }

        // 清理文件名中不安全/非法的字符（保留常见中英文、数字、空格和部分符号）
        $safeBaseName = preg_replace('/[^\p{L}\p{N}\s._()\-\[\]]/u', '_', $baseOriginalName);
        $safeBaseName = trim($safeBaseName);
        if ($safeBaseName === '') {
            $safeBaseName = 'file';
        }

        // 重新组装文件名，确保扩展名来自已校验的 $fileExtension
        $newFileName = $safeBaseName . ($fileExtension ? ('.' . $fileExtension) : '');
        $uploadPath = $uploadDir . $newFileName;

        // 若重名则追加序号
        $counter = 1;
        while (file_exists($uploadPath)) {
            $candidate = $safeBaseName . '(' . $counter . ')' . ($fileExtension ? ('.' . $fileExtension) : '');
            $uploadPath = $uploadDir . $candidate;
            $newFileName = $candidate;
            $counter++;
        }
        
        // 移动文件
        if (move_uploaded_file($fileTmpName, $uploadPath)) {
            echo json_encode(['success' => true, 'message' => '文件上传成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '文件保存失败']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'delete' && isset($_POST['filename'])) {
        $filename = basename($_POST['filename']);
        $filePath = $uploadDir . $filename;
        
        if (file_exists($filePath) && unlink($filePath)) {
            echo json_encode(['success' => true, 'message' => '文件删除成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '文件删除失败']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'delete_all') {
        $files = getUploadedFiles($uploadDir);
        $deletedCount = 0;
        $totalCount = count($files);
        
        foreach ($files as $file) {
            $filePath = $uploadDir . $file['name'];
            if (file_exists($filePath) && unlink($filePath)) {
                $deletedCount++;
            }
        }
        
        if ($deletedCount === $totalCount) {
            echo json_encode(['success' => true, 'message' => "成功删除 {$deletedCount} 个文件"]);
        } else {
            echo json_encode(['success' => false, 'message' => "删除了 {$deletedCount}/{$totalCount} 个文件，部分文件删除失败"]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'create_text' && isset($_POST['content'])) {
        $content = $_POST['content'];
        if (empty(trim($content))) {
            echo json_encode(['success' => false, 'message' => '文本内容不能为空']);
            exit;
        }
        
        // 生成文件名：年月日时分秒.txt
        $filename = date('YmdHis') . '.txt';
        $filePath = $uploadDir . $filename;
        
        // 写入文件内容
        if (file_put_contents($filePath, $content) !== false) {
            echo json_encode(['success' => true, 'message' => '文本文件创建成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '文本文件创建失败']);
        }
        exit;
    }
}

// 处理文件下载
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $filename = basename($_GET['download']);
    $filePath = $uploadDir . $filename;
    
    if (file_exists($filePath)) {
        // 使用保存时的原始文件名
        $originalName = $filename;

        // 基于文件类型设置 Content-Type
        $mimeType = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($filePath);
            if ($detected) { $mimeType = $detected; }
        }

        // ASCII 回退名，避免部分旧 UA 中文名乱码或 header 注入
        $asciiFallback = preg_replace('/[^\x20-\x7E]/', '_', $originalName);
        if ($asciiFallback === '') { $asciiFallback = 'download'; }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        // 同时设置 filename 与 filename* (RFC 5987) 以支持 UTF-8 文件名
        $disposition = 'attachment; filename="' . $asciiFallback . '"; filename*=UTF-8\'\'' . rawurlencode($originalName);
        header('Content-Disposition: ' . $disposition);

        readfile($filePath);
        exit;
    }
}

// 获取上传的文件列表
function getUploadedFiles($dir) {
    $files = [];
    if (is_dir($dir)) {
        // 使用 scandir 获取文件并在 PHP 层排序，避免一次性渲染过多导致前端卡顿。
        $fileList = scandir($dir);
        foreach ($fileList as $file) {
            if ($file !== '.' && $file !== '..' && is_file($dir . $file)) {
                $filePath = $dir . $file;
                $mtime = filemtime($filePath);
                $files[] = [
                    'name' => $file,
                    'size' => filesize($filePath),
                    'date' => date('Y-m-d H:i:s', $mtime),
                    'timestamp' => $mtime,
                    'type' => mime_content_type($filePath)
                ];
            }
        }
        // 按上传时间倒序排列（最新在前）
        usort($files, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
    }
    return $files;
}

// 分页参数：超过 100 条进行分页，默认每页 100
$allFiles = getUploadedFiles($uploadDir);
$totalCount = count($allFiles);
$perPage = 100;
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;
$uploadedFiles = array_slice($allFiles, $offset, $perPage);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件上传管理系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
            font-weight: 300;
        }

        .header p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .main-content {
            padding: 40px;
        }

        .upload-section {
            margin-bottom: 40px;
            text-align: center;
        }

        .upload-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(52, 152, 219, 0.3);
        }

        .upload-btn:hover {
            background: #2980b9;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.4);
        }

        .delete-all-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(231, 76, 60, 0.3);
            margin-left: 15px;
        }

        .delete-all-btn:hover {
            background: #c0392b;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(231, 76, 60, 0.4);
        }

        .text-upload-btn {
            background: #9b59b6;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(155, 89, 182, 0.3);
            margin-left: 15px;
        }

        .text-upload-btn:hover {
            background: #8e44ad;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(155, 89, 182, 0.4);
        }

        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .file-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease;
            border: 1px solid #e0e0e0;
        }

        .file-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
        }

        .file-preview {
            width: 100%;
            height: 200px;
            border-radius: 6px;
            margin-bottom: 15px;
            overflow: hidden;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e0e0e0;
        }

        .file-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }

        .file-preview .file-icon {
            font-size: 3rem;
            color: #95a5a6;
        }

        .file-name {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
            word-break: break-all;
            font-size: 0.95rem;
        }

        .file-info {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .file-actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-download {
            background: #27ae60;
            color: white;
        }

        .btn-download:hover {
            background: #229954;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .btn-preview {
            background: #f39c12;
            color: white;
        }

        .btn-preview:hover {
            background: #e67e22;
        }

        /* 模态框样式 */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 700px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .modal-header {
            background: #2c3e50;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-weight: 300;
        }

        .close {
            color: white;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.2s ease;
        }

        .close:hover {
            opacity: 0.7;
        }

        .modal-body {
            padding: 30px;
        }

        .drop-zone {
            border: 2px dashed #bdc3c7;
            border-radius: 8px;
            padding: 50px 20px;
            text-align: center;
            transition: all 0.2s ease;
            background: #f8f9fa;
            margin-bottom: 20px;
        }

        .drop-zone.dragover {
            border-color: #3498db;
            background: rgba(52, 152, 219, 0.05);
        }

        .drop-zone-icon {
            font-size: 2.5rem;
            color: #bdc3c7;
            margin-bottom: 15px;
        }

        .drop-zone.dragover .drop-zone-icon {
            color: #3498db;
        }

        .file-input {
            display: none;
        }

        .file-input-label {
            background: #3498db;
            color: white;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-block;
            transition: all 0.2s ease;
            margin-top: 15px;
        }

        .file-input-label:hover {
            background: #2980b9;
        }

        .upload-progress {
            display: none;
            margin-top: 20px;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #ecf0f1;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #3498db;
            width: 0%;
            transition: width 0.3s ease;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* 图片预览模态框 */
        .image-preview-modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
        }

        /* 视频预览模态框 */
        .video-preview-modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
        }

        .image-preview-content {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .image-preview-content img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            /* 允许缩放与拖拽 */
            transform-origin: center center;
            will-change: transform;
        }

        .video-preview-content {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .video-preview-content video {
            max-width: 90%;
            max-height: 90%;
            background: #000;
            border-radius: 6px;
        }

        /* 预览交互指针样式 */
        .image-preview-content.dragging {
            cursor: grabbing;
        }

        .image-preview-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 30px;
            cursor: pointer;
            transition: opacity 0.2s ease;
        }

        .video-preview-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 30px;
            cursor: pointer;
            transition: opacity 0.2s ease;
        }

        .image-preview-close:hover {
            opacity: 0.7;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 8px;
            }

            .header {
                padding: 20px;
            }

            .header h1 {
                font-size: 1.8rem;
            }

            .main-content {
                padding: 20px;
            }

            .upload-section {
                display: flex;
                flex-direction: column;
                gap: 15px;
                align-items: center;
            }

            .delete-all-btn,
            .text-upload-btn {
                margin-left: 0;
            }

            .files-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
            }

            .modal-body {
                padding: 20px;
            }

            .drop-zone {
                padding: 30px 15px;
            }

            /* 追加移动端增强优化 */
            body { padding: 12px; }
            .container { margin: 0; border-radius: 0; }
            .header { padding: 16px; }
            .main-content { padding: 16px; }

            .upload-section { width: 100%; gap: 10px; }
            .upload-section .upload-btn,
            .upload-section .text-upload-btn,
            .upload-section .delete-all-btn { width: 100%; padding: 12px 14px; font-size: 1rem; }

            .file-preview { height: 180px; }
            .file-actions { flex-wrap: wrap; }
            .file-actions .btn { flex: 1 1 calc(50% - 8px); justify-content: center; }

            /* 模态框在移动端使用全屏体验 */
            .modal-content { width: 100%; height: 100%; max-width: none; margin: 0; border-radius: 0; }
            .modal-header { padding: 16px; }
            .modal-body { padding: 16px; }

            /* 二维码浮窗适配 */
            .qr-float { right: 16px; bottom: 16px; width: 52px; height: 52px; }
            .qr-panel { right: 12px; bottom: 80px; width: calc(100% - 24px); }
        }

        /* 通知样式 */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1002;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .notification.success {
            background: #27ae60;
        }

        .notification.error {
            background: #e74c3c;
        }

        .notification.show {
            transform: translateX(0);
        }

        /* 文本输入样式 */
        .text-input-section {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .text-label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
        }

        .text-area {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
            resize: vertical;
            min-height: 300px;
            transition: border-color 0.2s ease;
        }

        .text-area:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .text-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-save {
            background: #27ae60;
            color: white;
        }

        .btn-save:hover {
            background: #229954;
        }

        .btn-cancel {
            background: #95a5a6;
            color: white;
        }

        .btn-cancel:hover {
            background: #7f8c8d;
        }

        /* 文本预览样式 */
        .text-preview-section {
            max-height: 500px;
            overflow-y: auto;
        }

        .text-preview-content {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
            color: #2c3e50;
            margin: 0;
            max-height: 400px;
            overflow-y: auto;
        }
        
        /* ===== 高级主题增强（覆盖与细化）===== */
        :root {
            --bg-start: #eef2f7;
            --bg-end: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.86);
            --border: rgba(15, 23, 42, 0.08);
            --ring: rgba(52, 152, 219, 0.35);
            --text: #1f2937;
            --muted: #6b7280;
            --primary: #2563eb;
            --primary-2: #1d4ed8;
            --success: #16a34a;
            --success-2: #15803d;
            --warning: #f59e0b;
            --warning-2: #d97706;
            --danger: #ef4444;
            --danger-2: #dc2626;
            --shadow-md: 0 10px 30px rgba(15, 23, 42, 0.08);
            --shadow-lg: 0 18px 50px rgba(15, 23, 42, 0.12);
        }

        body {
            background: radial-gradient(1200px 600px at 10% 0%, rgba(37, 99, 235, 0.08), rgba(37, 99, 235, 0) 60%),
                        radial-gradient(1000px 500px at 90% 10%, rgba(16, 185, 129, 0.07), rgba(16, 185, 129, 0) 60%),
                        linear-gradient(180deg, var(--bg-start), var(--bg-end));
            color: var(--text);
        }

        .container {
            backdrop-filter: saturate(140%) blur(8px);
            background: var(--card-bg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-md);
        }

        .header {
            background: linear-gradient(135deg, #0f172a, #0b5ed7 60%, #1b9aaa);
            box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.06);
        }

        .header h1 {
            letter-spacing: 0.5px;
            text-shadow: 0 1px 0 rgba(0,0,0,0.2);
        }

        .header p {
            color: rgba(255, 255, 255, 0.85);
        }

        .main-content {
            padding: 48px;
        }

        .upload-section {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 14px;
        }

        /* 按钮统一风格 */
        .btn, .upload-btn, .text-upload-btn, .delete-all-btn, .file-input-label {
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 1px 0 rgba(255,255,255,0.35) inset, 0 6px 18px rgba(0,0,0,0.08);
            transform: translateZ(0);
        }

        .upload-btn, .file-input-label {
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
        }

        .upload-btn:hover, .file-input-label:hover {
            box-shadow: 0 1px 0 rgba(255,255,255,0.35) inset, 0 12px 26px rgba(37, 99, 235, 0.35);
            transform: translateY(-1px);
        }

        .text-upload-btn {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .text-upload-btn:hover {
            box-shadow: 0 1px 0 rgba(255,255,255,0.35) inset, 0 12px 26px rgba(124, 58, 237, 0.35);
            transform: translateY(-1px);
        }

        .delete-all-btn {
            background: linear-gradient(135deg, var(--danger), var(--danger-2));
        }

        .delete-all-btn:hover {
            box-shadow: 0 1px 0 rgba(255,255,255,0.35) inset, 0 12px 26px rgba(220, 38, 38, 0.35);
            transform: translateY(-1px);
        }

        /* 文件卡片 */
        .file-card {
            border: 1px solid var(--border);
            background: linear-gradient(180deg, rgba(255,255,255,0.92), rgba(255,255,255,0.86));
            box-shadow: var(--shadow-md);
        }

        .file-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-4px);
        }

        .file-preview {
            background: linear-gradient(180deg, #f8fafc, #f1f5f9);
            border: 1px solid var(--border);
        }

        .file-name {
            font-size: 1rem;
            color: #0f172a;
        }

        .file-info {
            color: var(--muted);
        }

        .btn-download { background: linear-gradient(135deg, var(--success), var(--success-2)); }
        .btn-download:hover { box-shadow: 0 1px 0 rgba(255,255,255,0.35) inset, 0 10px 22px rgba(22, 163, 74, 0.35); }
        .btn-preview  { background: linear-gradient(135deg, var(--warning), var(--warning-2)); }
        .btn-preview:hover  { box-shadow: 0 1px 0 rgba(255,255,255,0.35) inset, 0 10px 22px rgba(245, 158, 11, 0.35); }
        .btn-delete   { background: linear-gradient(135deg, var(--danger), var(--danger-2)); }
        .btn-delete:hover   { box-shadow: 0 1px 0 rgba(255,255,255,0.35) inset, 0 10px 22px rgba(239, 68, 68, 0.35); }

        /* 分享按钮 */
        .btn-share { background: linear-gradient(135deg, var(--txc-primary), var(--txc-primary-hover)); color: #fff; }
        .btn-share:hover { box-shadow: 0 1px 0 rgba(255,255,255,0.35) inset, 0 10px 22px rgba(0, 82, 217, 0.35); }
        .btn .icon { width: 16px; height: 16px; display: inline-block; }
        .upload-btn .icon, .text-upload-btn .icon { width: 18px; height: 18px; }

        /* 模态框优化 */
        .modal-content {
            background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(255,255,255,0.9));
            border: 1px solid var(--border);
            backdrop-filter: saturate(140%) blur(10px);
        }

        .modal-header {
            background: linear-gradient(135deg, #0f172a, #0b5ed7 60%);
            box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.08);
        }

        /* 进度条优化 */
        .progress-bar {
            background: rgba(15, 23, 42, 0.06);
        }
        .progress-fill {
            background: linear-gradient(90deg, var(--primary), #22c55e);
        }

        /* 通知优化 */
        .notification {
            border: 1px solid rgba(255,255,255,0.45);
            backdrop-filter: blur(8px) saturate(160%);
        }

        /* ===== 腾讯云风格覆盖（简约 / 高级 / 企业）===== */
        :root {
            --txc-bg: #f7f8fa;
            --txc-card: #ffffff;
            --txc-text: #1d2129;
            --txc-text-2: #4e5969;
            --txc-border: #e5eaf3;
            --txc-primary: #0052d9; /* Tencent Blue */
            --txc-primary-hover: #0960f5;
            --txc-primary-press: #003cab;
            --txc-success: #2ba471;
            --txc-warning: #ed7b2f;
            --txc-danger: #d54941;
            --txc-shadow-sm: 0 6px 18px rgba(0, 0, 0, 0.06);
            --txc-shadow-md: 0 10px 24px rgba(0, 0, 0, 0.08);
        }

        body {
            background: radial-gradient(900px 450px at 85% -10%, rgba(0, 82, 217, 0.06), rgba(0, 82, 217, 0) 60%), var(--txc-bg);
            color: var(--txc-text);
        }

        .container {
            background: var(--txc-card);
            border: 1px solid var(--txc-border);
            box-shadow: var(--txc-shadow-sm);
            backdrop-filter: none;
        }

        .header {
            background: linear-gradient(135deg, var(--txc-primary), var(--txc-primary-press));
            box-shadow: none;
        }

        .header h1 {
            font-weight: 500;
            text-shadow: none;
        }

        .header p { color: rgba(255, 255, 255, 0.9); }

        .main-content { padding: 40px; }

        /* 按钮：扁平化企业风 */
        .btn, .upload-btn, .text-upload-btn, .delete-all-btn, .file-input-label {
            border: 1px solid rgba(0,0,0,0.02);
            box-shadow: none;
        }

        .upload-btn, .file-input-label {
            background: var(--txc-primary);
        }
        .upload-btn:hover, .file-input-label:hover { background: var(--txc-primary-hover); }
        .upload-btn:active, .file-input-label:active { background: var(--txc-primary-press); }

        .text-upload-btn { background: #6f42c1; }
        .text-upload-btn:hover { background: #5a37a2; }

        .delete-all-btn { background: var(--txc-danger); }
        .delete-all-btn:hover { background: #b93530; }

        .btn-download { background: var(--txc-success); }
        .btn-download:hover { background: #238a5e; }
        .btn-preview { background: var(--txc-warning); }
        .btn-preview:hover { background: #c96420; }
        .btn-delete { background: var(--txc-danger); }
        .btn-delete:hover { background: #b93530; }

        /* 文件卡片：更轻的阴影与更清晰边框 */
        .file-card {
            background: var(--txc-card);
            border: 1px solid var(--txc-border);
            box-shadow: var(--txc-shadow-sm);
        }
        .file-card:hover {
            box-shadow: var(--txc-shadow-md);
            transform: translateY(-2px);
        }

        .file-preview {
            background: #f3f6fb;
            border: 1px solid var(--txc-border);
        }
        .file-name { color: var(--txc-text); font-weight: 600; }
        .file-info { color: var(--txc-text-2); }

        /* 模态框：顶部栏使用腾讯蓝 */
        .modal-content {
            background: var(--txc-card);
            border: 1px solid var(--txc-border);
            backdrop-filter: none;
        }
        .modal-header { background: var(--txc-primary); }

        /* 进度条：腾讯蓝渐变 */
        .progress-bar { background: #edf2fc; }
        .progress-fill { background: linear-gradient(90deg, var(--txc-primary), var(--txc-primary-hover)); }

        /* 通知：企业气泡 */
        .notification {
            border: 1px solid var(--txc-border);
            background: #ffffff;
            color: var(--txc-text);
            box-shadow: var(--txc-shadow-sm);
        }
        .notification.success { background: #e8faf0; color: #18794e; }
        .notification.error { background: #fff1f0; color: #a61d24; }
        
        /* ===== 右侧二维码浮窗 ===== */
        .qr-float {
            position: fixed;
            right: 24px;
            bottom: 84px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--txc-primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(0, 82, 217, 0.25);
            z-index: 1100;
            user-select: none;
            transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }
        .qr-float:hover { 
            background: var(--txc-primary-hover); 
            transform: translateY(-2px);
            box-shadow: 0 16px 32px rgba(0, 82, 217, 0.35);
        }

        .qr-panel {
            position: fixed;
            right: 24px;
            bottom: 154px;
            width: 280px;
            background: #fff;
            border: 1px solid var(--txc-border);
            border-radius: 12px;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.12);
            z-index: 1101;
            display: none;
            overflow: hidden;
        }
        .qr-panel-header {
            background: var(--txc-primary);
            color: #fff;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .qr-panel-title { font-size: 14px; font-weight: 500; }
        .qr-close { cursor: pointer; opacity: 0.9; }
        .qr-close:hover { opacity: 1; }
        .qr-panel-body { padding: 16px; display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .qr-box { width: 200px; height: 200px; }
        .qr-tip { color: var(--txc-text-2); font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>颜某仁的文件上传管理系统</h1>
            <p>支持图片、视频、文档、文本和压缩包文件上传</p>
        </div>

        <div class="main-content">
            <div class="upload-section">
                <button class="upload-btn" onclick="openUploadModal()">
                    <span class="icon" aria-hidden="true">
                        <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="18" height="18">
                            <path d="M768.7168 365.9264c-15.9232-125.6448-123.136-222.8224-253.0816-222.8224S278.4256 240.2816 262.5024 365.9264c-116.1216 25.088-203.1616 128.3072-203.1616 251.9552 0 142.3872 115.456 257.8432 257.8432 257.8432h396.9024c142.3872 0 257.8432-115.456 257.8432-257.8432-0.0512-123.5968-87.0912-226.8672-203.2128-251.9552z" fill="currentColor"></path>
                            <path d="M591.872 571.5968v189.8496c0 15.104-12.2368 27.3408-27.3408 27.3408H466.688c-15.104 0-27.3408-12.2368-27.3408-27.3408v-189.8496H352.0512c-23.9616 0-36.352-28.6208-19.9168-46.08l163.5328-174.2336a27.33568 27.33568 0 0 1 39.8848 0l163.5328 174.2336c16.384 17.4592 3.9936 46.08-19.9168 46.08H591.872z" fill="currentColor"></path>
                        </svg>
                    </span>
                    上传文件
                </button>
                <button class="text-upload-btn" onclick="openTextModal()">
                    <span class="icon" aria-hidden="true">
                        <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="18" height="18">
                            <path d="M641.536 0l294.912 294.912v654.08c0 39.168-31.744 70.912-70.976 70.912H135.04A70.976 70.976 0 0 1 64 948.992V70.912C64 31.808 95.744 0 134.976 0h506.56z m26.304 677.184H288.448a33.216 33.216 0 0 0 0 63.872h379.392a33.216 33.216 0 0 0 0-63.872z m0-193.536H288.448a32.576 32.576 0 0 0 0 63.872h379.392a32.576 32.576 0 0 0 0-63.872zM484.352 303.552a33.216 33.216 0 0 0-36.224-12.8H288.448a33.216 33.216 0 0 0 0 63.872h159.68a33.216 33.216 0 0 0 36.224-51.072z" fill="currentColor"></path>
                            <path d="M640 0l294.912 294.912h-224A70.976 70.976 0 0 1 640 224V0z" fill="currentColor"></path>
                        </svg>
                    </span>
                    创建文本
                </button>
                <?php if ($totalCount > 0): ?>
                <button class="delete-all-btn" onclick="deleteAllFiles()">
                    🗑️ 删除全部文件
                </button>
                <?php endif; ?>
            </div>

            <div class="files-section">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="color:#4e5969; font-size:14px;">共 <?php echo $totalCount; ?> 个文件</div>
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination" style="display:flex; gap:8px; align-items:center;">
                        <?php $prevPage = max(1, $page - 1); $nextPage = min($totalPages, $page + 1); ?>
                        <a class="btn" href="?page=1" style="background:#f3f6fb;">首页</a>
                        <a class="btn" href="?page=<?php echo $prevPage; ?>" style="background:#f3f6fb;">上一页</a>
                        <span style="font-size:14px; color:#4e5969;">第 <?php echo $page; ?> / <?php echo $totalPages; ?> 页</span>
                        <a class="btn" href="?page=<?php echo $nextPage; ?>" style="background:#f3f6fb;">下一页</a>
                        <a class="btn" href="?page=<?php echo $totalPages; ?>" style="background:#f3f6fb;">末页</a>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($uploadedFiles)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📂</div>
                        <h3>暂无上传文件</h3>
                        <p>点击上传按钮开始上传您的文件</p>
                    </div>
                <?php else: ?>
                    <div class="files-grid">
                        <?php foreach ($uploadedFiles as $file): ?>
                            <div class="file-card">
                                <div class="file-preview">
                                    <?php if (strpos($file['type'], 'image/') === 0): ?>
                                        <img src="<?php echo $uploadDir . htmlspecialchars($file['name']); ?>" 
                                             alt="<?php echo htmlspecialchars($file['name']); ?>"
                                             onclick="previewImage('<?php echo $uploadDir . htmlspecialchars($file['name']); ?>')">
                                    <?php elseif (strpos($file['type'], 'video/') === 0 || preg_match('/\\.(mp4|webm|ogg)$/i', $file['name'])): ?>
                                        <div class="file-icon">🎬</div>
                                    <?php else: ?>
                                        <div class="file-icon"><?php echo getFileIcon($file['type']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="file-name"><?php echo htmlspecialchars(getOriginalFileName($file['name'])); ?></div>
                                <div class="file-info">
                                    大小: <?php echo formatFileSize($file['size']); ?><br>
                                    上传时间: <?php echo $file['date']; ?>
                                </div>
                                <div class="file-actions">
                                    <a href="?download=<?php echo urlencode($file['name']); ?>" 
                                       class="btn btn-download">
                                        <span class="icon" aria-hidden="true">
                                            <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="16" height="16">
                                                <path d="M511.957333 0c282.752 0 512 229.248 512 512s-229.248 512-512 512-512-229.248-512-512 229.248-512 512-512z m0 256a42.666667 42.666667 0 0 0-42.666666 42.666667v337.92l-144-115.2-4.394667-3.114667a42.666667 42.666667 0 0 0-48.938667 69.717333l213.333334 170.666667a42.666667 42.666667 0 0 0 53.333333 0l213.333333-170.666667 3.968-3.626666a42.666667 42.666667 0 0 0-57.301333-63.018667L554.666667 636.501333V298.666667a42.666667 42.666667 0 0 0-37.674667-42.368z" fill="currentColor"></path>
                                            </svg>
                                        </span>
                                        下载
                                    </a>
                                    <?php if (strpos($file['type'], 'image/') === 0): ?>
                                        <button class="btn btn-preview" 
                                                onclick="previewImage('<?php echo $uploadDir . htmlspecialchars($file['name']); ?>')">
                                            <span class="icon" aria-hidden="true">
                                                <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="16" height="16">
                                                    <path d="M70.5 511c0 243.3 197.2 440.5 440.5 440.5S951.6 754.3 951.6 511 754.3 70.5 511 70.5 70.5 267.7 70.5 511z" fill="currentColor"></path>
                                                    <path d="M511 694.6c-155.5 0-281.5-158.2-281.5-189.6 0-22.6 126-189.7 281.5-189.7 155.4 0 281.5 168.6 281.5 189.7 0 31.5-126.1 189.6-281.5 189.6zM507.4 405c-75.2 0-162.9 33.5-162.9 104.4s87.7 96.9 162.9 96.9 164.3-25.9 164.3-96.9S582.6 405 507.4 405z" fill="#FFFFFF"></path>
                                                    <path d="M564.8 495.9c6.7 0 12.9-1.2 18.8-3 0.2 2 0.8 3.9 0.8 6 0 33.8-33 61.2-73.4 61.2-40.5 0-73.4-27.4-73.4-61.2 0-33.8 32.9-61.2 73.4-61.2 2.1 0 4 0.4 6 0.5-2.2 4.7-3.5 9.6-3.5 14.9 0 23.6 23.1 42.8 51.3 42.8z" fill="#27323A"></path>
                                                </svg>
                                            </span>
                                            预览
                                        </button>
                                    <?php elseif (strpos($file['type'], 'video/') === 0 || preg_match('/\\.(mp4|webm|ogg)$/i', $file['name'])): ?>
                                        <button class="btn btn-preview"
                                                onclick="previewVideo('<?php echo $uploadDir . htmlspecialchars($file['name']); ?>')">
                                            <span class="icon" aria-hidden="true">
                                                <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="16" height="16">
                                                    <path d="M512 64C264.6 64 64 264.6 64 512s200.6 448 448 448 448-200.6 448-448S759.4 64 512 64zm-96 640V320l320 192-320 192z" fill="currentColor"/>
                                                </svg>
                                            </span>
                                            预览
                                        </button>
                                    <?php elseif ($file['type'] === 'text/plain'): ?>
                                        <button class="btn btn-preview" 
                                                onclick="previewText('<?php echo $uploadDir . htmlspecialchars($file['name']); ?>', '<?php echo htmlspecialchars(getOriginalFileName($file['name'])); ?>')">
                                            <span class="icon" aria-hidden="true">
                                                <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="16" height="16">
                                                    <path d="M70.5 511c0 243.3 197.2 440.5 440.5 440.5S951.6 754.3 951.6 511 754.3 70.5 511 70.5 70.5 267.7 70.5 511z" fill="currentColor"></path>
                                                    <path d="M511 694.6c-155.5 0-281.5-158.2-281.5-189.6 0-22.6 126-189.7 281.5-189.7 155.4 0 281.5 168.6 281.5 189.7 0 31.5-126.1 189.6-281.5 189.6zM507.4 405c-75.2 0-162.9 33.5-162.9 104.4s87.7 96.9 162.9 96.9 164.3-25.9 164.3-96.9S582.6 405 507.4 405z" fill="#FFFFFF"></path>
                                                    <path d="M564.8 495.9c6.7 0 12.9-1.2 18.8-3 0.2 2 0.8 3.9 0.8 6 0 33.8-33 61.2-73.4 61.2-40.5 0-73.4-27.4-73.4-61.2 0-33.8 32.9-61.2 73.4-61.2 2.1 0 4 0.4 6 0.5-2.2 4.7-3.5 9.6-3.5 14.9 0 23.6 23.1 42.8 51.3 42.8z" fill="#27323A"></path>
                                                </svg>
                                            </span>
                                            预览
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-share" 
                                            onclick="shareFile('<?php echo $uploadDir . htmlspecialchars($file['name']); ?>')">
                                        <span class="icon" aria-hidden="true">
                                            <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="16" height="16">
                                                <path d="M512.1024 51.2c-254.464 0-460.8 206.336-460.8 460.8s206.336 460.8 460.8 460.8S972.8 766.464 972.8 512 766.464 51.2 512.1024 51.2z m68.5056 661.0944v-136.192S344.4736 549.888 206.4384 746.496c0 0 43.4176-374.784 374.1696-374.784V235.52l237.9776 238.3872c0.1024 0-237.9776 238.3872-237.9776 238.3872z" fill="currentColor"></path>
                                            </svg>
                                        </span>
                                        分享
                                    </button>
                                    <button class="btn btn-delete" 
                                            onclick="deleteFile('<?php echo htmlspecialchars($file['name']); ?>')">
                                        <span class="icon" aria-hidden="true">
                                            <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="16" height="16">
                                                <path d="M512 0C229.376 0 0 229.376 0 512s229.376 512 512 512 512-229.376 512-512S794.624 0 512 0z m202.24 683.52c10.24 10.24 10.24 26.112 0 36.352s-26.112 10.24-36.352 0l-167.936-167.936-167.424 167.424c-10.24 10.24-26.112 10.24-36.352 0s-10.24-26.112 0-36.352l167.424-167.424-168.96-169.984c-10.24-10.24-10.24-26.112 0-36.352s26.112-10.24 36.352 0l169.472 169.472 169.984-169.984c10.24-10.24 26.112-10.24 36.352 0s10.24 26.112 0 36.352l-169.984 169.984 167.424 168.448z" fill="currentColor"></path>
                                            </svg>
                                        </span>
                                        删除
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div style="display:flex; justify-content:center; margin-top:20px;">
                        <div class="pagination" style="display:flex; gap:8px; align-items:center;">
                            <?php $prevPage = max(1, $page - 1); $nextPage = min($totalPages, $page + 1); ?>
                            <a class="btn" href="?page=1" style="background:#f3f6fb;">首页</a>
                            <a class="btn" href="?page=<?php echo $prevPage; ?>" style="background:#f3f6fb;">上一页</a>
                            <span style="font-size:14px; color:#4e5969;">第 <?php echo $page; ?> / <?php echo $totalPages; ?> 页</span>
                            <a class="btn" href="?page=<?php echo $nextPage; ?>" style="background:#f3f6fb;">下一页</a>
                            <a class="btn" href="?page=<?php echo $totalPages; ?>" style="background:#f3f6fb;">末页</a>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 上传模态框 -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>上传文件</h2>
                <span class="close" onclick="closeUploadModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="drop-zone" id="dropZone">
                    <div class="drop-zone-icon">📁</div>
                    <h3>拖拽文件到此处</h3>
                    <p>或者点击下方按钮选择文件</p>
                    <p style="color:#4e5969; font-size:13px; margin-top:6px;">提示：打开此窗口后，可直接粘贴图片上传</p>
                    <label for="fileInput" class="file-input-label">选择文件</label>
                    <input type="file" id="fileInput" class="file-input" multiple>
                </div>
                <div class="upload-progress" id="uploadProgress">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <p id="progressText">上传中...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 图片预览模态框 -->
    <div id="imagePreviewModal" class="image-preview-modal">
        <div class="image-preview-content">
            <img id="previewImage" src="" alt="图片预览">
            <span class="image-preview-close" onclick="closeImagePreview()">&times;</span>
        </div>
    </div>

    <!-- 视频预览模态框 -->
    <div id="videoPreviewModal" class="video-preview-modal">
        <div class="video-preview-content">
            <video id="previewVideo" controls preload="metadata"></video>
            <span class="video-preview-close" onclick="closeVideoPreview()">&times;</span>
        </div>
    </div>

    <!-- 右侧二维码浮窗 -->
    <div id="qrFloat" class="qr-float" title="页面二维码">
        <span class="icon" aria-hidden="true">
            <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="22" height="22">
                <path d="M512.1024 51.2c-254.464 0-460.8 206.336-460.8 460.8s206.336 460.8 460.8 460.8S972.8 766.464 972.8 512 766.464 51.2 512.1024 51.2z m68.5056 661.0944v-136.192S344.4736 549.888 206.4384 746.496c0 0 43.4176-374.784 374.1696-374.784V235.52l237.9776 238.3872c0.1024 0-237.9776 238.3872-237.9776 238.3872z" fill="currentColor"></path>
            </svg>
        </span>
    </div>
    <div id="qrPanel" class="qr-panel">
        <div class="qr-panel-header">
            <div class="qr-panel-title">页面二维码</div>
            <div id="qrClose" class="qr-close">✕</div>
        </div>
        <div class="qr-panel-body">
            <div id="qrBox" class="qr-box"></div>
            <div class="qr-tip">使用手机扫描二维码，快速在手机端打开此页面</div>
        </div>
    </div>

    <!-- 文本创建模态框 -->
    <div id="textModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>创建文本文件</h2>
                <span class="close" onclick="closeTextModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="text-input-section">
                    <label for="textContent" class="text-label">文本内容：</label>
                    <textarea id="textContent" class="text-area" placeholder="请输入文本内容..." rows="15"></textarea>
                    <div class="text-actions">
                        <button class="btn btn-save" onclick="saveTextFile()">💾 保存文件</button>
                        <button class="btn btn-cancel" onclick="closeTextModal()">❌ 取消</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 文本预览模态框 -->
    <div id="textPreviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="textPreviewTitle">文本预览</h2>
                <span class="close" onclick="closeTextPreview()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="text-preview-section">
                    <pre id="textPreviewContent" class="text-preview-content"></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- 分享模态框 -->
    <div id="shareModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>分享文件</h2>
                <span class="close" onclick="closeShareModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="text-input-section" style="align-items:center;">
                    <div id="shareQR" class="qr-box" style="border:1px solid var(--txc-border); border-radius:8px;"></div>
                    <div class="text-label" style="margin-top:12px;">文件直链：</div>
                    <div style="display:flex; gap:8px; width:100%;">
                        <input id="shareLink" type="text" readonly style="flex:1; padding:10px 12px; border:1px solid var(--txc-border); border-radius:6px; font-size:14px;" />
                        <button class="btn btn-share" onclick="copyShareLink()">复制链接</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 轻量二维码生成（最简实现：使用第三方API图片，避免引库体积）
        function openQRPanel() {
            const panel = document.getElementById('qrPanel');
            const box = document.getElementById('qrBox');
            const url = window.location.href;

            // 使用公开二维码API生成PNG（也可换成本地库，如 QRCode.js）
            const api = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(url);
            box.innerHTML = '<img src="' + api + '" alt="QR" width="200" height="200" />';
            panel.style.display = 'block';
        }

        function closeQRPanel() {
            const panel = document.getElementById('qrPanel');
            panel.style.display = 'none';
        }

        (function initQRFloat(){
            const btn = document.getElementById('qrFloat');
            const panel = document.getElementById('qrPanel');
            const closeBtn = document.getElementById('qrClose');
            let open = false;
            btn.addEventListener('click', function(){
                open = !open;
                if (open) openQRPanel(); else closeQRPanel();
            });
            closeBtn.addEventListener('click', function(){
                open = false;
                closeQRPanel();
            });
            // 点击外部关闭
            window.addEventListener('click', function(e){
                if (!open) return;
                const within = panel.contains(e.target) || btn.contains(e.target);
                if (!within) {
                    open = false; closeQRPanel();
                }
            });
        })();

        // 文件分享
        function shareFile(filePath) {
            // 使用当前页面完整地址作为基址，确保解析为 /pan/uploads/... 路径
            const absolute = new URL(filePath, window.location.href).href;
            const linkInput = document.getElementById('shareLink');
            linkInput.value = absolute;

            const shareQR = document.getElementById('shareQR');
            const api = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(absolute);
            shareQR.innerHTML = '<img src="' + api + '" alt="QR" width="200" height="200" />';

            document.getElementById('shareModal').style.display = 'block';
        }

        function closeShareModal() {
            document.getElementById('shareModal').style.display = 'none';
        }

        function copyShareLink() {
            const input = document.getElementById('shareLink');
            input.select();
            input.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                showNotification('链接已复制', 'success');
            } catch (e) {
                navigator.clipboard.writeText(input.value).then(() => showNotification('链接已复制', 'success')).catch(() => showNotification('复制失败', 'error'));
            }
        }
        // 模态框控制
        function openUploadModal() {
            const modal = document.getElementById('uploadModal');
            modal.style.display = 'block';
            // 注册粘贴监听（仅在模态框打开时生效）
            window.addEventListener('paste', handlePasteUpload);
        }

        function closeUploadModal() {
            const modal = document.getElementById('uploadModal');
            modal.style.display = 'none';
            // 卸载粘贴监听
            window.removeEventListener('paste', handlePasteUpload);
            resetUploadForm();
        }

        function openTextModal() {
            document.getElementById('textModal').style.display = 'block';
            document.getElementById('textContent').focus();
        }

        function closeTextModal() {
            document.getElementById('textModal').style.display = 'none';
            document.getElementById('textContent').value = '';
        }

        function closeTextPreview() {
            document.getElementById('textPreviewModal').style.display = 'none';
        }

        function resetUploadForm() {
            document.getElementById('fileInput').value = '';
            document.getElementById('uploadProgress').style.display = 'none';
            document.getElementById('progressFill').style.width = '0%';
        }

        // 图片预览功能
        function previewImage(imageSrc) {
            document.getElementById('previewImage').src = imageSrc;
            document.getElementById('imagePreviewModal').style.display = 'block';
        }

        function closeImagePreview() {
            document.getElementById('imagePreviewModal').style.display = 'none';
        }

        // 文本预览功能
        function previewText(filePath, fileName) {
            fetch(filePath)
                .then(response => response.text())
                .then(content => {
                    document.getElementById('textPreviewTitle').textContent = '文本预览 - ' + fileName;
                    document.getElementById('textPreviewContent').textContent = content;
                    document.getElementById('textPreviewModal').style.display = 'block';
                })
                .catch(error => {
                    showNotification('无法读取文件内容: ' + error.message, 'error');
                });
        }

        // 视频预览功能
        function previewVideo(videoSrc) {
            const modal = document.getElementById('videoPreviewModal');
            const video = document.getElementById('previewVideo');
            video.src = videoSrc;
            video.currentTime = 0;
            video.play().catch(() => {});
            modal.style.display = 'block';
        }

        function closeVideoPreview() {
            const modal = document.getElementById('videoPreviewModal');
            const video = document.getElementById('previewVideo');
            try { video.pause(); } catch(e) {}
            video.removeAttribute('src');
            video.load();
            modal.style.display = 'none';
        }

        // 图片预览：缩放与拖拽交互
        (function enableImagePreviewInteractions() {
            const modal = document.getElementById('imagePreviewModal');
            const container = modal.querySelector('.image-preview-content');
            const img = document.getElementById('previewImage');

            let scale = 1;
            let translateX = 0;
            let translateY = 0;
            let isDragging = false;
            let lastClientX = 0;
            let lastClientY = 0;
            let dragStartTime = 0;

            const MIN_SCALE = 0.2;
            const MAX_SCALE = 8;
            const ZOOM_STEP = 0.1; // 每次滚轮缩放步进

            function applyTransform() {
                img.style.transform = `translate(${translateX}px, ${translateY}px) scale(${scale})`;
            }

            function resetView() {
                scale = 1;
                translateX = 0;
                translateY = 0;
                applyTransform();
            }

            // 限制图片在可视区域内
            function constrainImagePosition() {
                const containerRect = container.getBoundingClientRect();
                const imgRect = img.getBoundingClientRect();
                
                // 计算图片的边界
                const imgWidth = imgRect.width;
                const imgHeight = imgRect.height;
                const containerWidth = containerRect.width;
                const containerHeight = containerRect.height;
                
                // 计算允许的最大偏移量（图片边缘不能超出容器太多）
                const maxOffsetX = Math.max(0, (imgWidth - containerWidth) / 2);
                const maxOffsetY = Math.max(0, (imgHeight - containerHeight) / 2);
                
                // 限制平移范围
                translateX = Math.max(-maxOffsetX, Math.min(maxOffsetX, translateX));
                translateY = Math.max(-maxOffsetY, Math.min(maxOffsetY, translateY));
            }

            // 以鼠标位置为中心缩放
            function zoomAt(clientX, clientY, deltaY) {
                const rect = img.getBoundingClientRect();
                // 鼠标相对图片当前变换后的坐标
                const offsetX = clientX - (rect.left + rect.width / 2);
                const offsetY = clientY - (rect.top + rect.height / 2);

                const oldScale = scale;
                const zoomDirection = deltaY < 0 ? 1 : -1; // 向上放大，向下缩小
                scale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, scale + zoomDirection * ZOOM_STEP * scale));

                // 调整平移以保证以指针为中心缩放
                const scaleFactor = scale / oldScale;
                translateX = translateX - offsetX * (scaleFactor - 1);
                translateY = translateY - offsetY * (scaleFactor - 1);

                applyTransform();
                constrainImagePosition();
                applyTransform(); // 再次应用以确保约束生效
            }

            // 滚轮缩放（阻止页面滚动）
            container.addEventListener('wheel', function (e) {
                if (modal.style.display !== 'block') return;
                e.preventDefault();
                zoomAt(e.clientX, e.clientY, e.deltaY);
            }, { passive: false });

            // 拖拽查看
            container.addEventListener('mousedown', function (e) {
                if (modal.style.display !== 'block') return;
                e.preventDefault(); // 防止图片被拖拽
                isDragging = true;
                dragStartTime = Date.now();
                container.classList.add('dragging');
                lastClientX = e.clientX;
                lastClientY = e.clientY;
            });

            window.addEventListener('mousemove', function (e) {
                if (!isDragging) return;
                e.preventDefault();
                
                const dx = e.clientX - lastClientX;
                const dy = e.clientY - lastClientY;
                lastClientX = e.clientX;
                lastClientY = e.clientY;
                
                translateX += dx;
                translateY += dy;
                
                applyTransform();
                constrainImagePosition();
                applyTransform();
            });

            window.addEventListener('mouseup', function (e) {
                if (!isDragging) return;
                
                // 如果拖拽时间很短，可能是点击事件，不处理
                const dragDuration = Date.now() - dragStartTime;
                if (dragDuration < 50) {
                    isDragging = false;
                    container.classList.remove('dragging');
                    return;
                }
                
                isDragging = false;
                container.classList.remove('dragging');
            });

            // 双击重置
            container.addEventListener('dblclick', function (e) {
                e.preventDefault();
                resetView();
            });

            // 当打开预览时重置视图，避免上次状态残留
            const originalPreviewImage = window.previewImage;
            window.previewImage = function (imageSrc) {
                resetView();
                img.src = imageSrc;
                modal.style.display = 'block';
            }

            // 关闭时也重置，避免下次进入错位
            const originalClose = window.closeImagePreview;
            window.closeImagePreview = function () {
                modal.style.display = 'none';
                resetView();
            }
        })();

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modal = document.getElementById('uploadModal');
            if (event.target === modal) {
                closeUploadModal();
            }
            
            const textModal = document.getElementById('textModal');
            if (event.target === textModal) {
                closeTextModal();
            }
            
            const textPreviewModal = document.getElementById('textPreviewModal');
            if (event.target === textPreviewModal) {
                closeTextPreview();
            }
            
            const imageModal = document.getElementById('imagePreviewModal');
            if (event.target === imageModal) {
                closeImagePreview();
            }
        }

        // 拖拽上传功能
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');

        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            handleFiles(files);
        });

        fileInput.addEventListener('change', function(e) {
            const files = e.target.files;
            handleFiles(files);
        });

        // 处理文件上传
        function handleFiles(files) {
            if (files.length === 0) return;

            const MAX_SIZE = <?php echo getUploadMaxBytes(); ?>; // 与后端保持一致

            // 1) 本地体积预检：超限的直接提示并跳过
            const validFiles = [];
            for (let i = 0; i < files.length; i++) {
                const f = files[i];
                if (f.size > MAX_SIZE) {
                    const limitMb = Math.round(MAX_SIZE/1024/1024);
                    showNotification(`文件过大（>${limitMb}MB）：${f.name}`,'error');
                } else {
                    validFiles.push(f);
                }
            }

            if (validFiles.length === 0) return;

            const progressDiv = document.getElementById('uploadProgress');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');

            progressDiv.style.display = 'block';

            let uploadedCount = 0;
            const totalFiles = validFiles.length;

            const finalize = () => {
                if (uploadedCount === totalFiles) {
                    setTimeout(() => {
                        closeUploadModal();
                        location.reload();
                    }, 1000);
                }
            };

            for (let i = 0; i < validFiles.length; i++) {
                const file = validFiles[i];
                const formData = new FormData();
                formData.append('file', file);
                formData.append('action', 'upload');

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(async (response) => {
                    if (!response.ok) {
                        uploadedCount++;
                        const progress = (uploadedCount / totalFiles) * 100;
                        progressFill.style.width = progress + '%';
                        progressText.textContent = `上传中... ${uploadedCount}/${totalFiles}`;

                        if (response.status === 413) {
                            showNotification(`服务器拒绝：文件过大(413)。请压缩或分片后再试，或联系管理员扩大上传限制。`, 'error');
                        } else if (response.status === 415) {
                            showNotification(`不支持的文件类型(415)：${file.name}`, 'error');
                        } else {
                            let text = '';
                            try { text = await response.text(); } catch (e) {}
                            showNotification(`上传失败(${response.status})：${file.name}` + (text ? ` - ${text.substring(0,120)}...` : ''), 'error');
                        }
                        finalize();
                        return null;
                    }
                    // 尝试解析 JSON
                    try { return await response.json(); } catch (e) {
                        return { success: false, message: '上传失败：服务器返回异常响应' };
                    }
                })
                .then((data) => {
                    if (data === null) return; // 已处理非 200

                    uploadedCount++;
                    const progress = (uploadedCount / totalFiles) * 100;
                    progressFill.style.width = progress + '%';
                    progressText.textContent = `上传中... ${uploadedCount}/${totalFiles}`;

                    if (data.success) {
                        showNotification(data.message || '上传成功', 'success');
                    } else {
                        showNotification(data.message || '上传失败', 'error');
                    }
                    finalize();
                })
                .catch(error => {
                    uploadedCount++;
                    showNotification('上传失败: ' + error.message, 'error');
                    finalize();
                });
            }
        }

        // 粘贴图片上传：从剪贴板读取图片 items
        function handlePasteUpload(e) {
            const items = (e.clipboardData || window.clipboardData)?.items;
            if (!items || items.length === 0) return;
            const blobList = [];
            for (let i = 0; i < items.length; i++) {
                const it = items[i];
                if (it.kind === 'file' && it.type && it.type.startsWith('image/')) {
                    const blob = it.getAsFile();
                    if (blob) blobList.push(blob);
                }
            }
            if (blobList.length > 0) {
                // 将 Blob 转为 File，并复用 handleFiles
                const files = blobList.map((b, idx) => new File([b], `pasted-image-${Date.now()}-${idx}.png`, { type: b.type || 'image/png' }));
                handleFiles(files);
            }
        }

        // 删除文件
        function deleteFile(filename) {
            if (!confirm('确定要删除这个文件吗？')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('filename', filename);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('删除失败: ' + error.message, 'error');
            });
        }

        // 删除全部文件
        function deleteAllFiles() {
            if (!confirm('确定要删除所有文件吗？此操作不可恢复！')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_all');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('删除失败: ' + error.message, 'error');
            });
        }

        // 保存文本文件
        function saveTextFile() {
            const content = document.getElementById('textContent').value.trim();
            if (!content) {
                showNotification('请输入文本内容', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'create_text');
            formData.append('content', content);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeTextModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('保存失败: ' + error.message, 'error');
            });
        }

        // 显示通知
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('show');
            }, 100);

            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }
    </script>
</body>
</html>

<?php
// 辅助函数
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

function getFileCategory($mimeType) {
    if (strpos($mimeType, 'image/') === 0) {
        return 'image';
    } elseif (strpos($mimeType, 'video/') === 0) {
        return 'video';
    } elseif (strpos($mimeType, 'text/') === 0) {
        return 'text';
    } elseif (in_array($mimeType, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'])) {
        return 'document';
    } elseif (in_array($mimeType, ['application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed', 'application/x-tar', 'application/gzip'])) {
        return 'archive';
    } else {
        return 'other';
    }
}

function getFileIcon($mimeType) {
    $category = getFileCategory($mimeType);
    switch ($category) {
        case 'image': return '🖼️';
        case 'video': return '🎥';
        case 'document': return '📄';
        case 'text': return '📝';
        case 'archive': return '📦';
        default: return '📁';
    }
}

function getOriginalFileName($filename) {
    // 移除时间戳和唯一ID前缀，显示原始文件名
    $parts = explode('_', $filename);
    if (count($parts) >= 3) {
        array_shift($parts); // 移除时间戳
        array_shift($parts); // 移除唯一ID
        return implode('_', $parts);
    }
    return $filename;
}
?>

