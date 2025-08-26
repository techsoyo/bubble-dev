# fix_repo_secure.ps1 - Version Segura y Mejorada
# Uso:
#   Set-ExecutionPolicy -Scope Process -ExecutionPolicy RemoteSigned
#   .\fix_repo_secure.ps1

# Configuracion inicial
$ErrorActionPreference = 'Stop'

# --- FUNCIONES DE SEGURIDAD ---

function Write-SecurityLog {
  param(
    [string]$Message,
    [string]$Level = 'INFO'
  )

  $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
  $logEntry = "[$timestamp] [$Level] $Message"

  switch ($Level) {
    'ERROR' { Write-Host $logEntry -ForegroundColor Red }
    'WARNING' { Write-Host $logEntry -ForegroundColor Yellow }
    'SECURITY' { Write-Host $logEntry -ForegroundColor Magenta }
    default { Write-Host $logEntry -ForegroundColor Gray }
  }

  if ($script:logPath -and (Test-Path (Split-Path $script:logPath -Parent))) {
    try {
      Add-Content -Path $script:logPath -Value $logEntry -Encoding UTF8 -ErrorAction SilentlyContinue
    }
    catch {
      Write-Warning "No se pudo escribir al log: $($_.Exception.Message)"
    }
  }
}

function Test-SecurePath {
  param(
    [Parameter(Mandatory = $true)]
    [string]$Path,
    [Parameter(Mandatory = $true)]
    [string]$AllowedRoot
  )

  try {
    if (-not (Test-Path -Path $Path)) {
      throw "Ruta no encontrada: $Path"
    }

    if (-not (Test-Path -Path $AllowedRoot)) {
      throw "Directorio raiz no encontrado: $AllowedRoot"
    }

    $resolvedPath = (Resolve-Path -Path $Path).Path
    $resolvedRoot = (Resolve-Path -Path $AllowedRoot).Path

    if (-not $resolvedPath.StartsWith($resolvedRoot, [StringComparison]::OrdinalIgnoreCase)) {
      throw "SECURITY VIOLATION: Archivo fuera del directorio permitido. Path: $resolvedPath, Root: $resolvedRoot"
    }

    Write-SecurityLog "Ruta validada: $resolvedPath" -Level 'SECURITY'
    return $resolvedPath
  }
  catch {
    Write-SecurityLog "Error de validacion de ruta: $($_.Exception.Message)" -Level 'ERROR'
    throw
  }
}

function Invoke-SecureCommand {
  param(
    [Parameter(Mandatory = $true)]
    [string]$Command,
    [string[]]$Arguments = @()
  )

  try {
    Write-SecurityLog "Ejecutando: $Command con argumentos: $($Arguments -join ', ')" -Level 'SECURITY'

    $result = & $Command @Arguments 2>&1
    $exitCode = $LASTEXITCODE

    return @{
      ExitCode = $exitCode
      Output   = $result
    }
  }
  catch {
    Write-SecurityLog "Error ejecutando comando: $($_.Exception.Message)" -Level 'ERROR'
    throw
  }
}

function New-SecureBackup {
  param(
    [Parameter(Mandatory = $true)]
    [string]$SourcePath,
    [Parameter(Mandatory = $true)]
    [string]$BackupRoot,
    [Parameter(Mandatory = $true)]
    [string]$ProjectRoot
  )

  try {
    $validatedSource = Test-SecurePath -Path $SourcePath -AllowedRoot $ProjectRoot
    $relativePath = $validatedSource.Substring($ProjectRoot.Length).TrimStart('\', '/')
    $destinationPath = Join-Path -Path $BackupRoot -ChildPath $relativePath

    $destinationDir = Split-Path -Path $destinationPath -Parent
    if (-not (Test-Path -Path $destinationDir)) {
      New-Item -ItemType Directory -Path $destinationDir -Force | Out-Null
    }

    Copy-Item -Path $validatedSource -Destination $destinationPath -Force -ErrorAction Stop

    if (-not (Test-Path -Path $destinationPath)) {
      throw "Backup fallo: archivo no se creo en $destinationPath"
    }

    $originalSize = (Get-Item -Path $validatedSource).Length
    $backupSize = (Get-Item -Path $destinationPath).Length

    if ($originalSize -ne $backupSize) {
      throw "Backup corrupto: tamano no coinciden (Original: $originalSize, Backup: $backupSize)"
    }

    Write-SecurityLog "Backup exitoso: $relativePath" -Level 'INFO'
    return $destinationPath
  }
  catch {
    Write-SecurityLog "Error en backup: $($_.Exception.Message)" -Level 'ERROR'
    throw
  }
}

# --- FUNCIONES DE PROCESAMIENTO ---

function Set-SecureFile {
  param(
    [Parameter(Mandatory = $true)]
    [string]$Path,
    [Parameter(Mandatory = $true)]
    [string]$Content
  )

  try {
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $utf8NoBom)
    Write-SecurityLog "Archivo escrito: $(Split-Path $Path -Leaf)" -Level 'INFO'
  }
  catch {
    Write-SecurityLog "Error escribiendo archivo: $($_.Exception.Message)" -Level 'ERROR'
    throw
  }
}

function Update-EndpointFiles {
  param(
    [Parameter(Mandatory = $true)]
    [string]$ApiDirectory,
    [Parameter(Mandatory = $true)]
    [string]$ProjectRoot,
    [Parameter(Mandatory = $true)]
    [string]$BackupRoot
  )

  if (-not (Test-Path -Path $ApiDirectory)) {
    Write-SecurityLog "Directorio de API no encontrado: $ApiDirectory" -Level 'WARNING'
    return
  }

  $endpointFiles = Get-ChildItem -Recurse -Path $ApiDirectory -Include *.php -File -ErrorAction SilentlyContinue

  $endpointHeader = @'
<?php declare(strict_types=1);

$__apiBootCandidates = [
    __DIR__ . '/../../api/bootstrap.php',
    __DIR__ . '/../../../api/bootstrap.php',
    __DIR__ . '/../../../../api/bootstrap.php',
    __DIR__ . '/../../../../../api/bootstrap.php',
];

$__apiBootLoaded = false;
foreach ($__apiBootCandidates as $__p) {
    if (is_file($__p)) { 
        require_once $__p; 
        $__apiBootLoaded = true; 
        break; 
    }
}

if (!$__apiBootLoaded) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success'=>false,'message'=>'api/bootstrap.php no encontrado','data'=>null]);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

'@

  foreach ($file in $endpointFiles) {
    try {
      $validatedPath = Test-SecurePath -Path $file.FullName -AllowedRoot $ProjectRoot

      $originalContent = [System.IO.File]::ReadAllText($validatedPath, [System.Text.Encoding]::UTF8)

      if ([string]::IsNullOrWhiteSpace($originalContent)) {
        Write-SecurityLog "Archivo vacio ignorado: $($file.Name)" -Level 'WARNING'
        continue
      }

      $processedContent = $originalContent

      # Quitar BOM si existe
      if ($processedContent.Length -gt 0 -and $processedContent[0] -eq [char]0xFEFF) {
        $processedContent = $processedContent.Substring(1)
      }

      # Encontrar y mantener solo contenido despues de <?php
      $phpIndex = $processedContent.IndexOf('<?php')
      if ($phpIndex -ge 0) {
        $processedContent = $processedContent.Substring($phpIndex + 5)
      }

      # Limpiar contenido usando regex seguro
      $strictTypesPattern = [regex]::Escape('declare(strict_types=1);')
      $processedContent = $processedContent -replace $strictTypesPattern, ''

      # Quitar cierre PHP inicial
      $processedContent = $processedContent -replace '^\s*\?>\s*', ''

      # Quitar lineas basura - patrones especificos
      $processedContent = $processedContent -replace '(?m)^\s*\\\s*$', ''
      $processedContent = $processedContent -replace '(?m)^\s*\.\s*$', ''

      # Quitar imports innecesarios en endpoints
      $processedContent = $processedContent -replace '(?m)^\s*use\s+PDO\s*;\s*', ''
      $processedContent = $processedContent -replace '(?m)^\s*use\s+Exception\s*;\s*', ''

      $finalContent = $endpointHeader + $processedContent

      if ($finalContent -ne $originalContent) {
        New-SecureBackup -SourcePath $validatedPath -BackupRoot $BackupRoot -ProjectRoot $ProjectRoot
        Set-SecureFile -Path $validatedPath -Content $finalContent
        Write-SecurityLog "Procesado endpoint: $($file.Name)" -Level 'INFO'
      }
    }
    catch {
      Write-SecurityLog "Error procesando endpoint $($file.Name): $($_.Exception.Message)" -Level 'ERROR'
    }
  }
}

function Update-SecurityFiles {
  param(
    [Parameter(Mandatory = $true)]
    [string]$SecurityDirectory,
    [Parameter(Mandatory = $true)]
    [string]$ProjectRoot,
    [Parameter(Mandatory = $true)]
    [string]$BackupRoot
  )

  if (-not (Test-Path -Path $SecurityDirectory)) {
    Write-SecurityLog "Directorio Security no encontrado: $SecurityDirectory" -Level 'WARNING'
    return
  }

  $securityFiles = Get-ChildItem -Recurse -Path $SecurityDirectory -Include *.php -File -ErrorAction SilentlyContinue

  foreach ($file in $securityFiles) {
    try {
      $validatedPath = Test-SecurePath -Path $file.FullName -AllowedRoot $ProjectRoot

      $originalContent = [System.IO.File]::ReadAllText($validatedPath, [System.Text.Encoding]::UTF8)

      if ([string]::IsNullOrWhiteSpace($originalContent)) {
        continue
      }

      $processedContent = $originalContent

      # Quitar BOM
      if ($processedContent.Length -gt 0 -and $processedContent[0] -eq [char]0xFEFF) {
        $processedContent = $processedContent.Substring(1)
      }

      # Procesar contenido PHP
      $phpIndex = $processedContent.IndexOf('<?php')
      if ($phpIndex -ge 0) {
        $processedContent = $processedContent.Substring($phpIndex + 5)
      }

      # Limpiar declaraciones previas - usando patrones seguros
      $strictTypesPattern = [regex]::Escape('declare(strict_types=1);')
      $namespacePattern = 'namespace\s+[^;]+;'
            
      $processedContent = $processedContent -replace $strictTypesPattern, ''
      $processedContent = $processedContent -replace $namespacePattern, ''

      # Quitar lineas basura
      $processedContent = $processedContent -replace '(?m)^\s*\\\s*$', ''
      $processedContent = $processedContent -replace '(?m)^\s*\.\s*$', ''

      # Crear header para archivos Security
      $securityHeader = @'
<?php declare(strict_types=1);

namespace Security;

'@

      $finalContent = $securityHeader + $processedContent

      if ($finalContent -ne $originalContent) {
        New-SecureBackup -SourcePath $validatedPath -BackupRoot $BackupRoot -ProjectRoot $ProjectRoot
        Set-SecureFile -Path $validatedPath -Content $finalContent
        Write-SecurityLog "Procesado Security: $($file.Name)" -Level 'INFO'
      }
    }
    catch {
      Write-SecurityLog "Error procesando archivo Security $($file.Name): $($_.Exception.Message)" -Level 'ERROR'
    }
  }
}

function Repair-NullableParameters {
  param(
    [Parameter(Mandatory = $true)]
    [string]$SourceDirectory,
    [Parameter(Mandatory = $true)]
    [string]$ProjectRoot,
    [Parameter(Mandatory = $true)]
    [string]$BackupRoot
  )

  if (-not (Test-Path -Path $SourceDirectory)) {
    Write-SecurityLog "Directorio src no encontrado: $SourceDirectory" -Level 'WARNING'
    return
  }

  $sourceFiles = Get-ChildItem -Recurse -Path $SourceDirectory -Include *.php -File -ErrorAction SilentlyContinue

  # Patron para parametros implicitamente nullable
  $nullablePattern = '([,(]\s*)(?!\?)(\b(?:bool|int|float|string|array|callable|self|static|[A-Z_][A-Za-z0-9_\\]*)\b)\s+(\$[A-Za-z_][A-Za-z0-9_]*)\s*=\s*null'

  foreach ($file in $sourceFiles) {
    try {
      $validatedPath = Test-SecurePath -Path $file.FullName -AllowedRoot $ProjectRoot

      $originalContent = [System.IO.File]::ReadAllText($validatedPath, [System.Text.Encoding]::UTF8)

      if ([string]::IsNullOrWhiteSpace($originalContent)) {
        continue
      }

      # Aplicar correccion nullable
      $fixedContent = $originalContent -replace $nullablePattern, '$1?$2 $3 = null'

      if ($fixedContent -ne $originalContent) {
        New-SecureBackup -SourcePath $validatedPath -BackupRoot $BackupRoot -ProjectRoot $ProjectRoot
        Set-SecureFile -Path $validatedPath -Content $fixedContent
        Write-SecurityLog "Procesado nullable: $($file.Name)" -Level 'INFO'
      }
    }
    catch {
      Write-SecurityLog "Error procesando nullable en $($file.Name): $($_.Exception.Message)" -Level 'ERROR'
    }
  }
}

function Test-PhpSyntax {
  param(
    [Parameter(Mandatory = $true)]
    [string]$FilePath
  )

  try {
    # Validar que PHP esta disponible
    $phpCheck = Invoke-SecureCommand -Command 'php' -Arguments @('--version')
    if ($phpCheck.ExitCode -ne 0) {
      throw "PHP no esta disponible o no funciona correctamente"
    }

    $result = Invoke-SecureCommand -Command 'php' -Arguments @('-l', $FilePath)

    return @{
      Success  = ($result.ExitCode -eq 0 -and ($result.Output -join "`n") -match 'No syntax errors detected')
      Output   = $result.Output -join "`n"
      ExitCode = $result.ExitCode
    }
  }
  catch {
    Write-SecurityLog "Error en lint de $FilePath : $($_.Exception.Message)" -Level 'ERROR'
    return @{
      Success  = $false
      Output   = $_.Exception.Message
      ExitCode = -1
    }
  }
}

# --- SCRIPT PRINCIPAL ---

try {
  Write-SecurityLog "=== INICIO DEL SCRIPT DE REPARACION SEGURA ===" -Level 'INFO'

  # Validar directorio de trabajo
  $projectRoot = (Get-Location).Path
  $composerPath = Join-Path -Path $projectRoot -ChildPath 'composer.json'

  if (-not (Test-Path -Path $composerPath -PathType Leaf)) {
    throw "SECURITY: Este script debe ejecutarse desde el directorio 'backend/' (donde esta composer.json)"
  }

  Write-SecurityLog "Directorio de trabajo validado: $projectRoot" -Level 'SECURITY'

  # Verificar permisos de escritura
  try {
    $testFile = Join-Path -Path $projectRoot -ChildPath "test_permissions.tmp"
    New-Item -Path $testFile -ItemType File -Force | Out-Null
    Remove-Item -Path $testFile -Force
    Write-SecurityLog "Permisos de escritura verificados" -Level 'SECURITY'
  }
  catch {
    throw "SECURITY: Sin permisos de escritura suficientes en el directorio de trabajo"
  }

  # Definir directorios
  $srcDir = Join-Path -Path $projectRoot -ChildPath 'src'
  $apiDir = Join-Path -Path $projectRoot -ChildPath 'public/api'
  $securityDir = Join-Path -Path $srcDir -ChildPath 'Security'

  # Crear directorio de backup
  $timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
  $backupRoot = Join-Path -Path $projectRoot -ChildPath "backup_secure_$timestamp"
  New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null

  # Configurar logging
  $script:logPath = Join-Path -Path $backupRoot -ChildPath 'security.log'
  Write-SecurityLog "Backup creado en: $backupRoot" -Level 'INFO'
  Write-SecurityLog "Log de seguridad: $script:logPath" -Level 'INFO'

  # JWT: Instalacion segura
  Write-SecurityLog "Instalando/actualizando firebase/php-jwt..." -Level 'INFO'
  try {
    $removeOp = Invoke-SecureCommand -Command 'composer' -Arguments @('remove', 'firebase/php-jwt', '--no-interaction')
    if ($removeOp.ExitCode -eq 0) {
      Write-SecurityLog "Paquete JWT removido (si existia)" -Level 'INFO'
    }
  }
  catch {
    Write-SecurityLog "JWT no estaba instalado previamente" -Level 'INFO'
  }

  $installResult = Invoke-SecureCommand -Command 'composer' -Arguments @('require', 'firebase/php-jwt', '--no-interaction')
  if ($installResult.ExitCode -ne 0) {
    throw "Error instalando firebase/php-jwt: $($installResult.Output)"
  }

  $autoloadResult = Invoke-SecureCommand -Command 'composer' -Arguments @('dump-autoload', '-o')
  if ($autoloadResult.ExitCode -ne 0) {
    throw "Error en dump-autoload: $($autoloadResult.Output)"
  }

  Write-SecurityLog "JWT instalado y autoload actualizado" -Level 'INFO'

  # Procesar archivos
  Write-SecurityLog "Procesando endpoints..." -Level 'INFO'
  Update-EndpointFiles -ApiDirectory $apiDir -ProjectRoot $projectRoot -BackupRoot $backupRoot

  Write-SecurityLog "Procesando archivos Security..." -Level 'INFO'
  Update-SecurityFiles -SecurityDirectory $securityDir -ProjectRoot $projectRoot -BackupRoot $backupRoot

  Write-SecurityLog "Corrigiendo parametros nullable..." -Level 'INFO'
  Repair-NullableParameters -SourceDirectory $srcDir -ProjectRoot $projectRoot -BackupRoot $backupRoot

  # Autoload final
  Write-SecurityLog "Ejecutando dump-autoload final..." -Level 'INFO'
  $finalAutoload = Invoke-SecureCommand -Command 'composer' -Arguments @('dump-autoload', '-o')
  if ($finalAutoload.ExitCode -ne 0) {
    Write-SecurityLog "WARNING: Error en dump-autoload final: $($finalAutoload.Output)" -Level 'WARNING'
  }

  # Lint integral
  Write-SecurityLog "Ejecutando lint en todos los archivos PHP..." -Level 'INFO'
  $lintErrorsList = @()

  # Lint endpoints
  if (Test-Path -Path $apiDir) {
    $endpointFiles = Get-ChildItem -Recurse -Path $apiDir -Include *.php -File -ErrorAction SilentlyContinue
    foreach ($file in $endpointFiles) {
      try {
        $validatedPath = Test-SecurePath -Path $file.FullName -AllowedRoot $projectRoot
        $lintResult = Test-PhpSyntax -FilePath $validatedPath

        if (-not $lintResult.Success) {
          $lintErrorsList += "ENDPOINT: $($file.Name) - $($lintResult.Output)"
        }
      }
      catch {
        $lintErrorsList += "ENDPOINT: $($file.Name) - Error: $($_.Exception.Message)"
      }
    }
  }

  # Lint Security
  if (Test-Path -Path $securityDir) {
    $securityFiles = Get-ChildItem -Recurse -Path $securityDir -Include *.php -File -ErrorAction SilentlyContinue
    foreach ($file in $securityFiles) {
      try {
        $validatedPath = Test-SecurePath -Path $file.FullName -AllowedRoot $projectRoot
        $lintResult = Test-PhpSyntax -FilePath $validatedPath

        if (-not $lintResult.Success) {
          $lintErrorsList += "SECURITY: $($file.Name) - $($lintResult.Output)"
        }
      }
      catch {
        $lintErrorsList += "SECURITY: $($file.Name) - Error: $($_.Exception.Message)"
      }
    }
  }

  # Lint src completo
  if (Test-Path -Path $srcDir) {
    $allSrcFiles = Get-ChildItem -Recurse -Path $srcDir -Include *.php -File -ErrorAction SilentlyContinue
    foreach ($file in $allSrcFiles) {
      try {
        $validatedPath = Test-SecurePath -Path $file.FullName -AllowedRoot $projectRoot
        $lintResult = Test-PhpSyntax -FilePath $validatedPath

        if (-not $lintResult.Success) {
          $lintErrorsList += "SRC: $($file.Name) - $($lintResult.Output)"
        }
      }
      catch {
        $lintErrorsList += "SRC: $($file.Name) - Error: $($_.Exception.Message)"
      }
    }
  }

  # Mostrar resultados
  if ($lintErrorsList.Count -gt 0) {
    Write-SecurityLog "ERRORES DE LINT ENCONTRADOS:" -Level 'ERROR'
    foreach ($lintErrorItem in $lintErrorsList) {
      Write-SecurityLog $lintErrorItem -Level 'ERROR'
    }
    Write-SecurityLog "Total errores: $($lintErrorsList.Count)" -Level 'ERROR'
  }
  else {
    Write-SecurityLog "Lint OK en todos los archivos PHP." -Level 'INFO'
  }

  Write-SecurityLog "Backup creado en: $backupRoot" -Level 'INFO'
  Write-SecurityLog "=== SCRIPT COMPLETADO EXITOSAMENTE ===" -Level 'INFO'
}
catch {
  Write-SecurityLog "ERROR CRITICO: $($_.Exception.Message)" -Level 'ERROR'
  Write-SecurityLog "Linea: $($_.InvocationInfo.ScriptLineNumber)" -Level 'ERROR'
  Write-SecurityLog "=== SCRIPT TERMINADO CON ERRORES ===" -Level 'ERROR'

  if ($script:logPath) {
    Write-Host "`nConsulta el log de seguridad en: $script:logPath" -ForegroundColor Yellow
  }

  throw
}
finally {
  if ($script:logPath) {
    Write-SecurityLog "Log de seguridad guardado en: $script:logPath" -Level 'INFO'
  }
}