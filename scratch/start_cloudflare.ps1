# Stop any existing cloudflared processes
Get-Process -Name cloudflared -ErrorAction SilentlyContinue | Stop-Process -Force

$cloudflaredPath = "scratch\cloudflared.exe"
$logFile = "scratch\cloudflare.log"

Remove-Item $logFile -ErrorAction SilentlyContinue

# Download cloudflared if it doesn't exist
if (-not (Test-Path $cloudflaredPath)) {
    Write-Host "Downloading cloudflared.exe from Cloudflare..."
    $url = "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe"
    Invoke-WebRequest -Uri $url -OutFile $cloudflaredPath
    Write-Host "Download complete."
}

# Start cloudflared tunnel forwarding to localhost:8000
Write-Host "Starting Cloudflare tunnel on port 8000..."
Start-Process -FilePath $cloudflaredPath -ArgumentList "tunnel --url http://localhost:8000" -RedirectStandardError $logFile -NoNewWindow -PassThru

# Wait for URL to be generated
Start-Sleep -Seconds 8

if (Test-Path $logFile) {
    $content = Get-Content $logFile -Raw
    
    # Extract the trycloudflare.com URL
    if ($content -match "(https://[a-zA-Z0-9.-]+\.trycloudflare\.com)") {
        $tunnelUrl = $Matches[1]
        Write-Host "Successfully generated Cloudflare tunnel URL: $tunnelUrl"
        
        # Update .env file
        $envFile = ".env"
        $envContent = Get-Content $envFile -Raw
        $envContent = $envContent -replace "APP_URL=https://[a-zA-Z0-9.-]+", "APP_URL=$tunnelUrl"
        Set-Content -Path $envFile -Value $envContent -NoNewline
        Write-Host "Updated .env file with new APP_URL!"
        
        # Update shopify.app.toml
        $tomlFile = "shopify.app.toml"
        $tomlContent = Get-Content $tomlFile -Raw
        $tomlContent = $tomlContent -replace "application_url = `"https://[a-zA-Z0-9.-]+`"", "application_url = `"$tunnelUrl`""
        $tomlContent = $tomlContent -replace "url = `"https://[a-zA-Z0-9.-]+`"", "url = `"$tunnelUrl`""
        $tomlContent = $tomlContent -replace "https://[a-zA-Z0-9.-]+/authenticate", "$tunnelUrl/authenticate"
        Set-Content -Path $tomlFile -Value $tomlContent -NoNewline
        Write-Host "Updated shopify.app.toml with new URL!"
        
        # Clear Laravel Cache
        php artisan config:clear
        php artisan cache:clear
        
        # Deploy config to Shopify
        Write-Host "Deploying new configuration to Shopify Partner Dashboard..."
        npx shopify app deploy --allow-updates
        
        Write-Host "`nTo start Shopify App Dev, run this command in your terminal:"
        Write-Host "npx shopify app dev --localhost-port 8000 --tunnel-url $($tunnelUrl):8081 --theme-app-extension-port 9294`n"
    } else {
        Write-Host "Could not find URL in output yet. Let's wait another 7 seconds..."
        Start-Sleep -Seconds 7
        $content = Get-Content $logFile -Raw
        if ($content -match "(https://[a-zA-Z0-9.-]+\.trycloudflare\.com)") {
            $tunnelUrl = $Matches[1]
            Write-Host "Successfully generated Cloudflare tunnel URL: $tunnelUrl"
            
            # Update .env file
            $envFile = ".env"
            $envContent = Get-Content $envFile -Raw
            $envContent = $envContent -replace "APP_URL=https://[a-zA-Z0-9.-]+", "APP_URL=$tunnelUrl"
            Set-Content -Path $envFile -Value $envContent -NoNewline
            Write-Host "Updated .env file with new APP_URL!"
            
            # Update shopify.app.toml
            $tomlFile = "shopify.app.toml"
            $tomlContent = Get-Content $tomlFile -Raw
            $tomlContent = $tomlContent -replace "application_url = `"https://[a-zA-Z0-9.-]+`"", "application_url = `"$tunnelUrl`""
            $tomlContent = $tomlContent -replace "url = `"https://[a-zA-Z0-9.-]+`"", "url = `"$tunnelUrl`""
            $tomlContent = $tomlContent -replace "https://[a-zA-Z0-9.-]+/authenticate", "$tunnelUrl/authenticate"
            Set-Content -Path $tomlFile -Value $tomlContent -NoNewline
            Write-Host "Updated shopify.app.toml with new URL!"
            
            # Clear Laravel Cache
            php artisan config:clear
            php artisan cache:clear
            
            # Deploy config to Shopify
            Write-Host "Deploying new configuration to Shopify Partner Dashboard..."
            npx shopify app deploy --allow-updates
            
            Write-Host "`nTo start Shopify App Dev, run this command in your terminal:"
            Write-Host "npx shopify app dev --localhost-port 8000 --tunnel-url $($tunnelUrl):8081 --theme-app-extension-port 9294`n"
        } else {
            Write-Host "Failed to extract tunnel URL. Please check scratch/cloudflare.log"
        }
    }
} else {
    Write-Host "Log file not created. cloudflared might have failed to start."
}
