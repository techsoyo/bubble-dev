param([switch]$KeepMUI = $false)

Write-Host "== PRUNE FRONTEND DEPS ==" -ForegroundColor Cyan
Set-Location -Path (Split-Path $MyInvocation.MyCommand.Path)

# 1) Lucide (lo sustituimos con codemod)
pnpm remove lucide-react

# 2) Charts pesados
pnpm remove chart.js react-chartjs-2

# 3) AOS
pnpm remove aos

# 4) PDFJS (visor embebido)
pnpm remove pdfjs-dist

# 5) MUI + Emotion (solo si NO quieres mantener MUI)
if (-not $KeepMUI) {
  pnpm remove @mui/material @mui/icons-material @mui/system @emotion/react @emotion/styled
}

# 6) En la raíz (Playwright fuera si usas Cypress)
Set-Location ..\
pnpm remove @playwright/test

Write-Host "== DONE ==" -ForegroundColor Green
