<?php
/**
 * Script para download e descompactação de arquivos .zip
 * da pasta sistema.c8x.app/deploy/last/
 */

// Configurações
$baseUrl = 'https://sistema.c8x.app/deploy/last/';
$tmpDir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
$files = [
    'app_clinica.zip',
    'app_clinica_assets.zip',
    'app_clinica_main.zip'
];

// Função para limpar pasta
function cleanDirectory($dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
        return true;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath)) {
            cleanDirectory($filePath);
            rmdir($filePath);
        } else {
            unlink($filePath);
        }
    }
    return true;
}

// Função para fazer download
function downloadFile($url, $destination) {
    $ch = curl_init($url);
    $fp = fopen($destination, 'wb');
    
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    fclose($fp);
    
    return $result && $httpCode == 200;
}

// Função para descompactar
function extractZip($zipFile, $destination) {
    $zip = new ZipArchive();
    if ($zip->open($zipFile) === TRUE) {
        $zip->extractTo($destination);
        $zip->close();
        return true;
    }
    return false;
}

// Função para copiar recursivamente conteúdo de uma pasta para outra
function copyDirectory($source, $destination, $excludeFiles = [], &$stats = null) {
    if ($stats === null) {
        $stats = ['files' => 0, 'dirs' => 0, 'errors' => 0];
    }
    
    if (!is_dir($source)) {
        return false;
    }
    
    if (!is_dir($destination)) {
        if (!mkdir($destination, 0777, true)) {
            $stats['errors']++;
            return false;
        }
    }
    
    $items = scandir($source);
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        // Ignorar arquivos na lista de exclusão
        if (in_array($item, $excludeFiles)) {
            continue;
        }
        
        $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
        $destPath = $destination . DIRECTORY_SEPARATOR . $item;
        
        if (is_dir($sourcePath)) {
            // Copiar subdiretório recursivamente
            if (copyDirectory($sourcePath, $destPath, [], $stats)) {
                $stats['dirs']++;
            } else {
                $stats['errors']++;
            }
        } else {
            // Copiar arquivo (sobrescreve se já existir)
            if (copy($sourcePath, $destPath)) {
                $stats['files']++;
            } else {
                $stats['errors']++;
            }
        }
    }
    
    return $stats['errors'] == 0;
}

// Processar se o formulário foi enviado
$action = $_GET['action'] ?? '';
$messages = [];
$errors = [];

if ($action === 'process') {
    set_time_limit(600); // 10 minutos
    
    try {
        // Passo 1: Limpar pasta tmp
        $messages[] = "🔄 Limpando pasta tmp...";
        if (cleanDirectory($tmpDir)) {
            $messages[] = "✅ Pasta tmp limpa com sucesso!";
        } else {
            $errors[] = "❌ Erro ao limpar pasta tmp";
        }
        
        // Passo 2: Download dos arquivos
        foreach ($files as $file) {
            $url = $baseUrl . urlencode($file);
            $localPath = $tmpDir . DIRECTORY_SEPARATOR . $file;
            
            $messages[] = "⬇️ Baixando {$file}...";
            
            if (downloadFile($url, $localPath)) {
                $fileSize = filesize($localPath);
                $fileSizeMB = round($fileSize / 1024 / 1024, 2);
                $messages[] = "✅ {$file} baixado com sucesso! ({$fileSizeMB} MB)";
            } else {
                $errors[] = "❌ Erro ao baixar {$file}";
            }
        }
        
        // Passo 3: Descompactar arquivos
        foreach ($files as $file) {
            $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $file;
            
            if (file_exists($zipPath)) {
                $messages[] = "📦 Descompactando {$file}...";
                
                if (extractZip($zipPath, $tmpDir)) {
                    $messages[] = "✅ {$file} descompactado com sucesso!";
                    // Opcional: remover o arquivo .zip após descompactar
                    // unlink($zipPath);
                } else {
                    $errors[] = "❌ Erro ao descompactar {$file}";
                }
            } else {
                $errors[] = "❌ Arquivo {$file} não encontrado para descompactar";
            }
        }
        
        // Passo 4: Copiar conteúdo de tmp\ para pasta anterior (..)
        if (empty($errors)) {
            $messages[] = "📋 Copiando arquivos de tmp\ para pasta anterior (..)...";
            
            $rootDir = dirname(__DIR__); // Pasta anterior (pasta pai)
            $excludeFiles = array_merge($files, ['tmp']); // Excluir os .zip e a pasta tmp
            $stats = ['files' => 0, 'dirs' => 0, 'errors' => 0];
            
            if (copyDirectory($tmpDir, $rootDir, $excludeFiles, $stats)) {
                $messages[] = "✅ Arquivos copiados com sucesso! ({$stats['files']} arquivos, {$stats['dirs']} pastas)";
                $messages[] = "✅ Arquivos existentes foram sobrescritos na pasta anterior!";
            } else {
                if ($stats['errors'] > 0) {
                    $errors[] = "❌ Erro ao copiar alguns arquivos ({$stats['errors']} erros)";
                } else {
                    $errors[] = "❌ Erro ao copiar arquivos para pasta anterior";
                }
            }
        }
        
        if (empty($errors)) {
            $messages[] = "🎉 Processo concluído com sucesso!";
        }
        
    } catch (Exception $e) {
        $errors[] = "❌ Erro: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download e Descompactação de Arquivos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 800px;
            width: 100%;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 5px;
        }
        
        .info-box h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .info-box ul {
            list-style: none;
            padding-left: 0;
        }
        
        .info-box li {
            padding: 5px 0;
            color: #555;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .messages {
            margin-top: 30px;
        }
        
        .message {
            padding: 12px 15px;
            margin: 8px 0;
            border-radius: 6px;
            font-size: 14px;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .status {
            margin-top: 20px;
            padding: 15px;
            background: #e7f3ff;
            border-radius: 8px;
            border-left: 4px solid #2196F3;
        }
        
        .file-list {
            margin-top: 15px;
        }
        
        .file-item {
            padding: 8px;
            background: white;
            margin: 5px 0;
            border-radius: 4px;
            display: flex;
            align-items: center;
        }
        
        .file-item::before {
            content: "📄";
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📥 Download e Descompactação</h1>
        <p class="subtitle">Sistema de download e extração de arquivos .zip</p>
        
        <div class="info-box">
            <h3>📋 Arquivos que serão processados:</h3>
            <ul>
                <li>app_clinica.zip</li>
                <li>app_clinica_assets.zip</li>
                <li>app_clinica_main.zip</li>
            </ul>
        </div>
        
        <?php if ($action !== 'process'): ?>
            <form method="GET" action="">
                <input type="hidden" name="action" value="process">
                <button type="submit" class="btn">
                    🚀 Iniciar Processo
                </button>
            </form>
        <?php else: ?>
            <div class="status">
                <strong>⏳ Processando...</strong>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($messages) || !empty($errors)): ?>
            <div class="messages">
                <?php foreach ($messages as $msg): ?>
                    <div class="message success"><?php echo htmlspecialchars($msg); ?></div>
                <?php endforeach; ?>
                
                <?php foreach ($errors as $error): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($errors)): ?>
                <div style="margin-top: 20px;">
                    <a href="?" class="btn">🔄 Processar Novamente</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($action === 'process' && !empty($messages)): ?>
            <div class="status" style="margin-top: 20px;">
                <strong>✅ Processo Concluído!</strong>
                <p style="margin-top: 10px; color: #555;">
                    Os arquivos foram baixados, descompactados e copiados para a <strong>pasta anterior (..)</strong> (sobrescrevendo arquivos existentes).
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
