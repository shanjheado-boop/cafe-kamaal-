# 📱 Mobile Phone Installation Guide & APK Generation

Cafe Kamaal is fully equipped as a **Progressive Web App (PWA)** and can be installed on any **Android**, **iOS (iPhone/iPad)**, or **Desktop** device without needing Google Play Store or Apple App Store approval.

---

## ⚡ Method 1: Instant Installation on Mobile (Recommended)

Hostellers can install Cafe Kamaal directly from their mobile browser in 5 seconds. Once installed, it adds an official app icon to the phone home screen, opens full-screen without a browser URL bar, and functions exactly like a native app.

### 🤖 For Android Phones (Chrome / Edge / Brave / Samsung Internet):
1. Open your browser and navigate to your deployed Cafe Kamaal URL (e.g. `http://your-server-ip:8000` or `https://yourdomain.com`).
2. An **"Install Cafe Kamaal App"** banner will automatically slide down at the top of the screen.
3. Tap **Install App** (or tap the **⋮ (three dots)** menu in Chrome &rarr; tap **"Install app"** / **"Add to Home screen"**).
4. Tap **Install**. The Cafe Kamaal app icon will now appear on your phone home screen and app drawer!

---

### 🍏 For iPhone & iPad (Safari):
1. Open **Safari** on your iPhone and visit the Cafe Kamaal portal.
2. Tap the **Share** button (<i class="fa-solid fa-arrow-up-from-bracket"></i> square with an upward arrow at the bottom of the screen).
3. Scroll down and tap **"Add to Home Screen"** (<i class="fa-regular fa-square-plus"></i>).
4. Tap **Add** in the top right corner. The Cafe Kamaal app icon will now be placed on your iOS home screen!

---

## 📦 Method 2: Generate Standalone Android `.apk` File

If you want to send a standalone `.apk` install file directly to hostellers over WhatsApp or Bluetooth:

### Option A: Using PWABuilder (Easiest - 2 Minutes, Free, No Coding)
1. Deploy your Cafe Kamaal repository to any live hosting with HTTPS (e.g. Railway, Render, or a VPS).
2. Go to **[https://www.pwabuilder.com/](https://www.pwabuilder.com/)** (Official tool developed by Microsoft).
3. Enter your live website URL (e.g. `https://your-cafe-kamaal.up.railway.app`) and click **Start**.
4. PWABuilder will automatically read your [`manifest.json`](manifest.json) and verify all app icons (`assets/icons/icon-512.png`).
5. Click **Package for Stores** &rarr; select **Android**.
6. Download the generated `.apk` package.
7. Send the `.apk` file directly to your hostel group on WhatsApp. Anyone can tap the file to install it!

### Option B: Using Google Bubblewrap CLI (Command Line)
If you prefer building locally via terminal:
```bash
# 1. Install Bubblewrap CLI
npm install -g @bubblewrap/cli

# 2. Initialize project from your live URL
bubblewrap init --manifest="https://your-cafe-kamaal.com/manifest.json"

# 3. Build signed APK
bubblewrap build
```

---

## 🎨 App Features on Mobile Phones
- **Full-Screen Standalone Experience**: No browser address bars or navigation clutter.
- **Dedicated App Icons**: Crisp 192x192 & 512x512 high-resolution emblems.
- **Offline Fallback**: Offline screen informs members when network connection drops.
- **Touch-Optimized**: Large tap targets for selecting hosteller divider codes (A–Z).
- **Fast Access Shortcuts**: Direct home-screen long-press shortcuts for **Dashboard**, **Meal Log**, and **My Expenses**.
