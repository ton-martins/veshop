$ErrorActionPreference = 'Stop'

$projectPath = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$projectPathLower = $projectPath.ToLowerInvariant()

Write-Host '[veshop] Limpando instancias anteriores do Vite...'

# Evita múltiplas instâncias do Vite para o mesmo projeto.
Get-CimInstance Win32_Process |
    Where-Object {
        if ($_.Name -ne 'node.exe') {
            return $false
        }

        $cmd = [string] $_.CommandLine
        if ([string]::IsNullOrWhiteSpace($cmd)) {
            return $false
        }

        $cmdLower = $cmd.ToLowerInvariant()
        return $cmdLower.Contains($projectPathLower) -and ($cmdLower -match 'vite[\\/]+bin[\\/]+vite\.js')
    } |
    ForEach-Object {
        Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
    }

$hotFile = Join-Path $projectPath 'public\hot'
if (Test-Path $hotFile) {
    Remove-Item $hotFile -Force -ErrorAction SilentlyContinue
}

Write-Host '[veshop] Iniciando servidor em http://127.0.0.1:5173'

Set-Location $projectPath
npx vite --host 127.0.0.1 --port 5173 --strictPort --clearScreen false
