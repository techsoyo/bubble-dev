# Script para generar hashes SHA256 para CSP
# Usado para Content Security Policy

function Get-SHA256Hash {
  param([string]$content)
    
  $bytes = [System.Text.Encoding]::UTF8.GetBytes($content.Trim())
  $sha256 = [System.Security.Cryptography.SHA256]::store()
  $hashBytes = $sha256.ComputeHash($bytes)
  $base64Hash = [System.Convert]::ToBase64String($hashBytes)
    
  return $base64Hash
}

# Script 1: FOUC Prevention
$script1 = @"
    document.documentElement.classList.add('js-loading');
    window.addEventListener('DOMContentLoaded', () => {
      document.documentElement.classList.remove('js-loading');
    });

    // Verificar el estado de conexión
    if (navigator.onLine) {
      document.documentElement.setAttribute('data-connection', 'online');
    } else {
      document.documentElement.setAttribute('data-connection', 'offline');
    }
"@

# Script 2: DOM Content Loaded
$script2 = @"
    document.addEventListener('DOMContentLoaded', function () {
      // Ocultar overlay
      const overlay = document.querySelector('.video-overlay');
      if (overlay) {
        overlay.style.display = 'none';
      }

      console.log('Usando imagen de fondo: Rectangle-9386.png');

      // Intersection Observer para elementos que aparecen al hacer scroll
      const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
      };

      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
          }
        });
      }, observerOptions);

      // Observar elementos con la clase fade-in-scroll
      document.querySelectorAll('.fade-in-scroll').forEach(el => {
        observer.observe(el);
      });
    });
"@

# Generar hashes
$hash1 = Get-SHA256Hash -content $script1
$hash2 = Get-SHA256Hash -content $script2

Write-Host "=== HASHES SHA256 PARA CSP ===" -ForegroundColor Green
Write-Host "Script 1 (FOUC): 'sha256-$hash1'" -ForegroundColor Yellow
Write-Host "Script 2 (DOM): 'sha256-$hash2'" -ForegroundColor Yellow
Write-Host "" 
Write-Host "Para usar en CSP:" -ForegroundColor Cyan
Write-Host "script-src 'self' 'sha256-$hash1' 'sha256-$hash2'" -ForegroundColor White
