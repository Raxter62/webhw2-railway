// ====== Service Worker for 四系迎新 PWA ======
// 版本號:每次更新內容時要改這個
const CACHE_VERSION = 'yzu-orientation-v1.0.2';
const CACHE_NAME = `${CACHE_VERSION}`;

// 需要快取的核心資源
const CORE_ASSETS = [
  './',
  './index.html',
  './manifest.json',
  
  // CSS 檔案
  './css/bootstrap.min.css',
  './css/font-awesome.min.css',
  './css/templatemo_misc.css',
  './css/templatemo_style.css',
  
  // JavaScript 檔案
  './js/jquery-1.11.1.min.js',
  './js/templatemo_custom.js',
  './js/jquery.lightbox.js',
  './js/bootstrap-collapse.js',
  
  // 主要圖片
  './images/icon-192x192.png',
  './images/icon-512x512.png',
  './images/templatemo_header.png'
];

// ====== 安裝階段 ======
self.addEventListener('install', (event) => {
  console.log('[SW] 🔧 安裝中...', CACHE_VERSION);
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[SW] 📦 開始快取核心資源');
        // 使用 addAll 一次快取所有檔案
        return cache.addAll(CORE_ASSETS)
          .catch((err) => {
            console.error('[SW] ❌ 快取失敗:', err);
            // 即使部分失敗也繼續
            return Promise.resolve();
          });
      })
      .then(() => {
        console.log('[SW] ✅ 快取完成');
        // 強制啟用新的 SW
        return self.skipWaiting();
      })
  );
});

// ====== 啟用階段 ======
self.addEventListener('activate', (event) => {
  console.log('[SW] 🚀 啟用中...', CACHE_VERSION);
  
  event.waitUntil(
    // 清除舊版本的快取
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheName !== CACHE_NAME) {
              console.log('[SW] 🗑️ 刪除舊快取:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('[SW] ✅ 啟用完成');
        // 立即控制所有頁面
        return self.clients.claim();
      })
  );
});

// ====== 請求攔截 ======
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);
  
  // 忽略 Chrome extension 請求
  if (url.protocol === 'chrome-extension:') {
    return;
  }
  
  // 忽略跨域請求 (如 Google Fonts)
  if (url.origin !== location.origin) {
    event.respondWith(fetch(request));
    return;
  }
  
  // 策略: Cache First (優先使用快取)
  event.respondWith(
    caches.match(request)
      .then((cachedResponse) => {
        if (cachedResponse) {
          // 找到快取,直接返回
          console.log('[SW] 📂 使用快取:', url.pathname);
          return cachedResponse;
        }
        
        // 沒有快取,發起網路請求
        console.log('[SW] 🌐 網路請求:', url.pathname);
        return fetch(request)
          .then((networkResponse) => {
            // 如果是 GET 請求且成功,放入快取
            if (request.method === 'GET' && networkResponse.status === 200) {
              const responseClone = networkResponse.clone();
              caches.open(CACHE_NAME)
                .then((cache) => {
                  cache.put(request, responseClone);
                  console.log('[SW] 💾 已快取:', url.pathname);
                });
            }
            return networkResponse;
          })
          .catch((err) => {
            console.error('[SW] ❌ 請求失敗:', url.pathname, err);
            
            // 如果是 HTML 請求失敗,返回離線頁面(可選)
            if (request.headers.get('accept').includes('text/html')) {
              return caches.match('./index.html');
            }
            
            // 其他資源失敗就回傳錯誤
            return new Response('離線狀態', {
              status: 503,
              statusText: 'Service Unavailable'
            });
          });
      })
  );
});

// ====== 訊息處理 (可用於手動更新快取) ======
self.addEventListener('message', (event) => {
  console.log('[SW] 📬 收到訊息:', event.data);
  
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'CLEAR_CACHE') {
    caches.keys().then(keys => {
      return Promise.all(keys.map(key => caches.delete(key)));
    }).then(() => {
      console.log('[SW] 🗑️ 所有快取已清除');
      event.ports[0].postMessage({ success: true });
    });
  }
});

// ====== 背景同步 (可選功能) ======
self.addEventListener('sync', (event) => {
  console.log('[SW] 🔄 背景同步:', event.tag);
  
  if (event.tag === 'sync-registrations') {
    event.waitUntil(
      // 這裡可以處理離線時的表單提交
      syncRegistrations()
    );
  }
});

async function syncRegistrations() {
  console.log('[SW] 📤 同步報名資料...');
  // 實作離線表單同步邏輯
  return Promise.resolve();
}

// ====== 推送通知 (可選功能) ======
self.addEventListener('push', (event) => {
  console.log('[SW] 🔔 收到推送:', event.data?.text());
  
  const options = {
    body: event.data?.text() || '四系迎新有新消息!',
    icon: './images/icon-192x192.png',
    badge: './images/icon-72x72.png',
    vibrate: [200, 100, 200],
    tag: 'yzu-notification',
    actions: [
      { action: 'open', title: '查看' },
      { action: 'close', title: '關閉' }
    ]
  };
  
  event.waitUntil(
    self.registration.showNotification('四系迎新', options)
  );
});

self.addEventListener('notificationclick', (event) => {
  console.log('[SW] 🖱️ 點擊通知:', event.action);
  event.notification.close();
  
  if (event.action === 'open') {
    event.waitUntil(
      clients.openWindow('./')
    );
  }
});

console.log('[SW] 🎯 Service Worker 已載入:', CACHE_VERSION);