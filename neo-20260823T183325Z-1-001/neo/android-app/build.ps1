param(
    [switch]$SkipCopy
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$sdkRoot = if ($env:ANDROID_SDK_ROOT) {
    $env:ANDROID_SDK_ROOT
} elseif ($env:ANDROID_HOME) {
    $env:ANDROID_HOME
} else {
    Join-Path $env:LOCALAPPDATA 'Android\Sdk'
}

if (-not (Test-Path -LiteralPath $sdkRoot)) {
    throw "SDK Android não encontrado em $sdkRoot"
}

$buildToolsRoot = Join-Path $sdkRoot 'build-tools'
$buildTools = Get-ChildItem -LiteralPath $buildToolsRoot -Directory |
    Sort-Object { [version]($_.Name -replace '[^0-9.]', '') } -Descending |
    Select-Object -First 1
$platform = Get-ChildItem -LiteralPath (Join-Path $sdkRoot 'platforms') -Directory |
    Sort-Object { [version]($_.Name -replace '^android-', '') } -Descending |
    Select-Object -First 1

if (-not $buildTools -or -not $platform) {
    throw 'Build Tools ou plataforma Android não encontrados.'
}

$androidJar = Join-Path $platform.FullName 'android.jar'
$aapt2 = Join-Path $buildTools.FullName 'aapt2.exe'
$d8 = Join-Path $buildTools.FullName 'd8.bat'
$zipalign = Join-Path $buildTools.FullName 'zipalign.exe'
$apksigner = Join-Path $buildTools.FullName 'apksigner.bat'
$javac = (Get-Command javac -ErrorAction Stop).Source
$javaCandidates = @()
if ($env:JAVA_HOME) {
    $javaCandidates += $env:JAVA_HOME
}
$javaCandidates += Get-ChildItem -LiteralPath 'C:\Program Files\Java' -Directory -Filter 'jdk-*' -ErrorAction SilentlyContinue |
    Sort-Object Name -Descending |
    ForEach-Object { $_.FullName }
$javaRoot = $javaCandidates |
    Where-Object { Test-Path -LiteralPath (Join-Path $_ 'bin\jar.exe') } |
    Select-Object -First 1
if (-not $javaRoot) {
    throw 'JDK completo não encontrado.'
}
$jar = Join-Path $javaRoot 'bin\jar.exe'
$keytool = Join-Path $javaRoot 'bin\keytool.exe'

$buildDir = Join-Path $projectRoot 'build'
$classesDir = Join-Path $buildDir 'classes'
$dexDir = Join-Path $buildDir 'dex'
$compiledResources = Join-Path $buildDir 'resources.zip'
$unsignedApk = Join-Path $buildDir 'neo-unsigned.apk'
$alignedApk = Join-Path $buildDir 'neo-aligned.apk'
$signedApk = Join-Path $buildDir 'NEO-Remoto.apk'

$resolvedProject = [System.IO.Path]::GetFullPath($projectRoot).TrimEnd('\') + '\'
$resolvedBuild = [System.IO.Path]::GetFullPath($buildDir).TrimEnd('\') + '\'
if (-not $resolvedBuild.StartsWith($resolvedProject, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'Diretório de build fora do projeto; operação cancelada.'
}
if (Test-Path -LiteralPath $buildDir) {
    Remove-Item -LiteralPath $buildDir -Recurse -Force
}
New-Item -ItemType Directory -Path $classesDir, $dexDir | Out-Null

& $aapt2 compile --dir (Join-Path $projectRoot 'res') -o $compiledResources
if ($LASTEXITCODE -ne 0) { throw 'Falha ao compilar os recursos Android.' }

& $aapt2 link `
    -I $androidJar `
    --manifest (Join-Path $projectRoot 'AndroidManifest.xml') `
    --min-sdk-version 26 `
    --target-sdk-version 36 `
    --version-code 3 `
    --version-name '1.1.0' `
    --auto-add-overlay `
    -o $unsignedApk `
    $compiledResources
if ($LASTEXITCODE -ne 0) { throw 'Falha ao montar os recursos do APK.' }

$sources = Get-ChildItem -LiteralPath (Join-Path $projectRoot 'src') -Recurse -Filter '*.java' |
    ForEach-Object { $_.FullName }
& $javac -encoding UTF-8 -source 8 -target 8 -bootclasspath $androidJar -d $classesDir $sources
if ($LASTEXITCODE -ne 0) { throw 'Falha ao compilar o código Java.' }

$classFiles = Get-ChildItem -LiteralPath $classesDir -Recurse -Filter '*.class' |
    ForEach-Object { $_.FullName }
& $d8 --lib $androidJar --min-api 26 --output $dexDir $classFiles
if ($LASTEXITCODE -ne 0) { throw 'Falha ao gerar o código DEX.' }

& $jar uf $unsignedApk -C $dexDir 'classes.dex'
if ($LASTEXITCODE -ne 0) { throw 'Falha ao adicionar classes.dex ao APK.' }

& $zipalign -f -p 4 $unsignedApk $alignedApk
if ($LASTEXITCODE -ne 0) { throw 'Falha ao alinhar o APK.' }

$signingDir = Join-Path $projectRoot 'signing'
$keystore = Join-Path $signingDir 'neo-demo.jks'
$storePassword = 'neo-demo-2026'
if (-not (Test-Path -LiteralPath $keystore)) {
    New-Item -ItemType Directory -Path $signingDir -Force | Out-Null
    & $keytool -genkeypair `
        -keystore $keystore `
        -storepass $storePassword `
        -keypass $storePassword `
        -alias neo-demo `
        -keyalg RSA `
        -keysize 3072 `
        -sigalg SHA384withRSA `
        -validity 10000 `
        -dname 'CN=NEO Remoto Demo, O=NEO, C=BR' `
        -noprompt
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao criar a chave de demonstração.' }
}

& $apksigner sign `
    --ks $keystore `
    --ks-key-alias neo-demo `
    --ks-pass "pass:$storePassword" `
    --key-pass "pass:$storePassword" `
    --out $signedApk `
    $alignedApk
if ($LASTEXITCODE -ne 0) { throw 'Falha ao assinar o APK.' }

& $apksigner verify --verbose --print-certs $signedApk
if ($LASTEXITCODE -ne 0) { throw 'A assinatura do APK não foi validada.' }

if (-not $SkipCopy) {
    $downloadDir = Join-Path (Split-Path -Parent $projectRoot) 'downloads'
    $downloadApk = Join-Path $downloadDir 'NEO-Remoto.apk'
    $hashFile = Join-Path $downloadDir 'NEO-Remoto.sha256'
    New-Item -ItemType Directory -Path $downloadDir -Force | Out-Null
    Copy-Item -LiteralPath $signedApk -Destination $downloadApk -Force
    $hash = (Get-FileHash -Algorithm SHA256 -LiteralPath $downloadApk).Hash.ToLowerInvariant()
    Set-Content -LiteralPath $hashFile -Value "$hash  NEO-Remoto.apk" -Encoding ascii
    Write-Output "APK atualizado: $downloadApk"
    Write-Output "SHA-256: $hash"
}
