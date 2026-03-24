$path = "docs/SYSTEM_OVERVIEW.md"
$text = Get-Content -Raw $path
$start = "<!-- SNIPPETS_START -->"
$end = "<!-- SNIPPETS_END -->"
$si = $text.IndexOf($start)
$ei = $text.IndexOf($end)
if ($si -lt 0 -or $ei -lt 0 -or $ei -le $si) { throw "Snippet markers not found or invalid." }
$head = $text.Substring(0, $si)
$mid = $text.Substring($si, $ei - $si)
$tail = $text.Substring($ei)
$changed = 0
$total = 0
$mid2 = [regex]::Replace($mid, '(?ms)```([a-zA-Z0-9_-]*)\r?\n(.*?)\r?\n```', {
    param($m)
    $script:total++
    $lang = $m.Groups[1].Value
    $body = $m.Groups[2].Value -replace "`r", ""
    $lines = $body -split "`n"
    if ($lines.Count -gt 8) {
        $lines = $lines[0..7]
        $script:changed++
    }
    $newBody = ($lines -join "`r`n")
    return "```$lang`r`n$newBody`r`n```"
})
$newText = $head + $mid2 + $tail
Set-Content -Path $path -Value $newText -Encoding UTF8
Write-Output ("TOTAL_BLOCKS=" + $script:total)
Write-Output ("TRIMMED_BLOCKS=" + $script:changed)
