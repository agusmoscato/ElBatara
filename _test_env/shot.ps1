param(
  [string]$CdpPort = "9333",
  [string]$Url,
  [string]$OutFile,
  [string]$PhpSessId = "3d12as538e7pnroq64jao1td82",
  [int]$Width = 1440,
  [int]$Height = 900
)

Add-Type -AssemblyName System.Net.WebSockets 2>$null

function Send-Cdp($ws, $id, $method, $params) {
    $msg = @{ id = $id; method = $method; params = $params } | ConvertTo-Json -Compress -Depth 10
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($msg)
    $seg = New-Object System.ArraySegment[byte] (,$bytes)
    $ws.SendAsync($seg, [System.Net.WebSockets.WebSocketMessageType]::Text, $true, [System.Threading.CancellationToken]::None).Wait()
}

function Receive-Cdp($ws) {
    $buffer = New-Object byte[] 65536
    $all = New-Object System.Collections.Generic.List[byte]
    do {
        $seg = New-Object System.ArraySegment[byte] (,$buffer)
        $result = $ws.ReceiveAsync($seg, [System.Threading.CancellationToken]::None).GetAwaiter().GetResult()
        for ($i = 0; $i -lt $result.Count; $i++) { $all.Add($buffer[$i]) }
    } while (-not $result.EndOfMessage)
    return [System.Text.Encoding]::UTF8.GetString($all.ToArray())
}

function Receive-Until-Id($ws, $targetId) {
    while ($true) {
        $txt = Receive-Cdp $ws
        $obj = $txt | ConvertFrom-Json
        if ($obj.id -eq $targetId) { return $obj }
    }
}

$verJson = Invoke-RestMethod "http://127.0.0.1:$CdpPort/json/version"
$browserWs = $verJson.webSocketDebuggerUrl

$wsBrowser = New-Object System.Net.WebSockets.ClientWebSocket
$wsBrowser.ConnectAsync([Uri]$browserWs, [System.Threading.CancellationToken]::None).Wait()

# Create a new tab
Send-Cdp $wsBrowser 1 "Target.createTarget" @{ url = "about:blank" }
$resp = Receive-Until-Id $wsBrowser 1
$targetId = $resp.result.targetId

$newTabJson = Invoke-RestMethod "http://127.0.0.1:$CdpPort/json/list"
$tab = $newTabJson | Where-Object { $_.id -eq $targetId }
$tabWsUrl = $tab.webSocketDebuggerUrl

$ws = New-Object System.Net.WebSockets.ClientWebSocket
$ws.ConnectAsync([Uri]$tabWsUrl, [System.Threading.CancellationToken]::None).Wait()

$msgId = 1

Send-Cdp $ws $msgId "Page.enable" @{}
Receive-Until-Id $ws $msgId | Out-Null
$msgId++

Send-Cdp $ws $msgId "Emulation.setDeviceMetricsOverride" @{ width = $Width; height = $Height; deviceScaleFactor = 1; mobile = $false }
Receive-Until-Id $ws $msgId | Out-Null
$msgId++

Send-Cdp $ws $msgId "Network.setCookie" @{ name = "PHPSESSID"; value = $PhpSessId; domain = "127.0.0.1"; path = "/" }
Receive-Until-Id $ws $msgId | Out-Null
$msgId++

Send-Cdp $ws $msgId "Page.navigate" @{ url = $Url }
Receive-Until-Id $ws $msgId | Out-Null
$msgId++

# Wait for load event
$loaded = $false
$deadline = (Get-Date).AddSeconds(15)
while (-not $loaded -and (Get-Date) -lt $deadline) {
    $txt = Receive-Cdp $ws
    if ($txt -match '"method":"Page.loadEventFired"') { $loaded = $true }
}

Start-Sleep -Milliseconds 800

Send-Cdp $ws $msgId "Page.captureScreenshot" @{ format = "png" }
$shot = Receive-Until-Id $ws $msgId
$msgId++

$bytes = [Convert]::FromBase64String($shot.result.data)
[System.IO.File]::WriteAllBytes($OutFile, $bytes)

Send-Cdp $wsBrowser ($msgId+100) "Target.closeTarget" @{ targetId = $targetId }

Write-Output "Saved: $OutFile"
