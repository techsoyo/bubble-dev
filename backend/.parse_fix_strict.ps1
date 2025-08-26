$path = 'C:\laragon\www\bubble_of_talents_1.0\backend\fix_strict_types.ps1'
$errors = $null
[void][Management.Automation.Language.Parser]::ParseFile($path, [ref]$errors)
if ($errors) {
  foreach ($e in $errors) {
    Write-Host '---'
    Write-Host "Message: $($e.Message)"
    Write-Host "Line: $($e.Extent.StartLineNumber)"
    Write-Host "Text: $($e.Extent.Text)"
  }
  exit 1
}
else {
  Write-Host 'NO_PARSE_ERRORS'
  exit 0
}
