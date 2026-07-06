# Start localtunnel on port 8080 in the background
$outputFile = "scratch/tunnel_output.txt"
Remove-Item $outputFile -ErrorAction SilentlyContinue

Start-Process -FilePath "npx.cmd" -ArgumentList "localtunnel --port 8080" -RedirectStandardOutput $outputFile -NoNewWindow -PassThru

Write-Host "Starting localtunnel on port 8080..."
Start-Sleep -Seconds 5

if (Test-Path $outputFile) {
    $content = Get-Content $outputFile -Raw
    Write-Host "Tunnel output: $content"
    
    # Try to extract the URL (looks like https://*.loca.lt)
    if ($content -match "your url is: (https://[a-zA-Z0-9.-]+\.loca\.lt)") {
        $tunnelUrl = $Matches[1]
        Write-Host "Successfully generated tunnel URL: $tunnelUrl"
        
        # Update .env file
        $envFile = ".env"
        $envContent = Get-Content $envFile -Raw
        $envContent = $envContent -replace "APP_URL=https://[a-zA-Z0-9.-]+", "APP_URL=$tunnelUrl"
        Set-Content -Path $envFile -Value $envContent -NoNewline
        Write-Host "Updated .env file with new APP_URL!"
        
        # Also print the command to run
        Write-Host "`nTo start Shopify App Dev, run this command in your terminal:"
        Write-Host "npx shopify app dev --localhost-port 8080 --tunnel-url $tunnelUrl --theme-app-extension-port 9294`n"
    } else {
        Write-Host "Could not find URL in output yet. Let's wait another 5 seconds..."
        Start-Sleep -Seconds 5
        $content = Get-Content $outputFile -Raw
        if ($content -match "your url is: (https://[a-zA-Z0-9.-]+\.loca\.lt)") {
            $tunnelUrl = $Matches[1]
            Write-Host "Successfully generated tunnel URL: $tunnelUrl"
            
            # Update .env file
            $envFile = ".env"
            $envContent = Get-Content $envFile -Raw
            $envContent = $envContent -replace "APP_URL=https://[a-zA-Z0-9.-]+", "APP_URL=$tunnelUrl"
            Set-Content -Path $envFile -Value $envContent -NoNewline
            Write-Host "Updated .env file with new APP_URL!"
            
            Write-Host "`nTo start Shopify App Dev, run this command in your terminal:"
            Write-Host "npx shopify app dev --localhost-port 8080 --tunnel-url $tunnelUrl --theme-app-extension-port 9294`n"
        } else {
            Write-Host "Failed to extract tunnel URL. Please check scratch/tunnel_output.txt"
        }
    }
} else {
    Write-Host "Output file not created. localtunnel might have failed to start."
}
