package ar.edu.controlaula;

import android.app.Activity;
import android.app.AlertDialog;
import android.app.DownloadManager;
import android.content.Context;
import android.content.SharedPreferences;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Bundle;
import android.os.Environment;
import android.text.InputType;
import android.view.KeyEvent;
import android.webkit.CookieManager;
import android.webkit.DownloadListener;
import android.webkit.JavascriptInterface;
import android.webkit.JsPromptResult;
import android.webkit.JsResult;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.EditText;
import android.widget.Toast;

public class MainActivity extends Activity {

    private static final String PREFS_NAME = "ControlAulaPrefs";
    private static final String KEY_URL = "esp32_url";
    private static final String DEFAULT_URL = "http://192.168.4.1/";

    private WebView webView;
    private String currentBaseUrl;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        currentBaseUrl = prefs.getString(KEY_URL, DEFAULT_URL);

        webView = new WebView(this);
        setContentView(webView);

        WebSettings ws = webView.getSettings();
        ws.setJavaScriptEnabled(true);
        ws.setDomStorageEnabled(true);
        ws.setDatabaseEnabled(true);
        ws.setCacheMode(WebSettings.LOAD_DEFAULT);
        ws.setUseWideViewPort(true);
        ws.setLoadWithOverviewMode(true);
        ws.setSupportZoom(false);
        ws.setBuiltInZoomControls(false);
        ws.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);

        CookieManager cm = CookieManager.getInstance();
        cm.setAcceptCookie(true);
        cm.setAcceptThirdPartyCookies(webView, true);

        webView.addJavascriptInterface(new NativeBridge(), "AndroidApp");

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public boolean onJsAlert(WebView view, String url, String message, final JsResult result) {
                new AlertDialog.Builder(MainActivity.this)
                    .setTitle("Control de Aula")
                    .setMessage(message)
                    .setPositiveButton("Aceptar", (d, w) -> result.confirm())
                    .setOnCancelListener(d -> result.cancel())
                    .show();
                return true;
            }

            @Override
            public boolean onJsConfirm(WebView view, String url, String message, final JsResult result) {
                new AlertDialog.Builder(MainActivity.this)
                    .setTitle("Confirmar")
                    .setMessage(message)
                    .setPositiveButton("Sí", (d, w) -> result.confirm())
                    .setNegativeButton("Cancelar", (d, w) -> result.cancel())
                    .setOnCancelListener(d -> result.cancel())
                    .show();
                return true;
            }

            @Override
            public boolean onJsPrompt(WebView view, String url, String message, String defaultValue, final JsPromptResult result) {
                final EditText input = new EditText(MainActivity.this);
                input.setInputType(InputType.TYPE_CLASS_TEXT);
                input.setText(defaultValue);
                new AlertDialog.Builder(MainActivity.this)
                    .setTitle("Control de Aula")
                    .setMessage(message)
                    .setView(input)
                    .setPositiveButton("Aceptar", (d, w) -> result.confirm(input.getText().toString()))
                    .setNegativeButton("Cancelar", (d, w) -> result.cancel())
                    .setOnCancelListener(d -> result.cancel())
                    .show();
                return true;
            }
        });

        webView.setDownloadListener(new DownloadListener() {
            @Override
            public void onDownloadStart(String url, String userAgent, String contentDisposition, String mimeType, long contentLength) {
                try {
                    DownloadManager.Request req = new DownloadManager.Request(Uri.parse(url));
                    String cookies = CookieManager.getInstance().getCookie(url);
                    if (cookies != null) req.addRequestHeader("Cookie", cookies);
                    req.setMimeType(mimeType);
                    req.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
                    String fileName = url.endsWith(".apk") ? "ControlAula.apk" : "historial_control_aula.csv";
                    req.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, fileName);
                    DownloadManager dm = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
                    if (dm != null) {
                        dm.enqueue(req);
                        Toast.makeText(MainActivity.this, "Descargando " + fileName + "...", Toast.LENGTH_SHORT).show();
                    }
                } catch (Exception ex) {
                    Toast.makeText(MainActivity.this, "No se pudo descargar el archivo", Toast.LENGTH_SHORT).show();
                }
            }
        });

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                CookieManager.getInstance().flush();
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request != null && request.isForMainFrame()) {
                    mostrarPantallaDesconectado();
                }
            }

            @SuppressWarnings("deprecation")
            @Override
            public void onReceivedError(WebView view, int errorCode, String description, String failingUrl) {
                mostrarPantallaDesconectado();
            }
        });

        webView.loadUrl(currentBaseUrl);
    }

    private void mostrarPantallaDesconectado() {
        String safeUrl = currentBaseUrl.replace("\"", "");
        String html = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'>"
            + "<meta name='viewport' content='width=device-width,initial-scale=1'>"
            + "<style>"
            + "body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:#181b1f;color:#eef2f6;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;box-sizing:border-box;}"
            + ".card{background:#212529;border:1px solid #373b3e;border-radius:14px;padding:24px;max-width:380px;width:100%;text-align:center;box-shadow:0 10px 28px rgba(0,0,0,.45);}"
            + "h1{font-size:20px;margin:0 0 8px;color:#fff;}p{font-size:14px;color:#adb5bd;line-height:1.45;margin:0 0 16px;}"
            + ".box{background:#2b3035;border-radius:10px;padding:12px;text-align:left;font-size:13px;margin-bottom:16px;border:1px solid #3c4248;}"
            + ".box b{color:#6ea8fe;}input{width:100%;padding:10px;border-radius:8px;border:1px solid #495057;background:#181b1f;color:#fff;font-size:14px;box-sizing:border-box;margin-bottom:10px;}"
            + "button{width:100%;padding:12px;border:0;border-radius:8px;background:#0d6efd;color:#fff;font-size:15px;font-weight:700;cursor:pointer;margin-bottom:8px;}"
            + "button.sec{background:#343a40;font-weight:600;font-size:13px;}"
            + "</style></head><body><div class='card'>"
            + "<h1>Sin conexión al ESP32</h1>"
            + "<p>Conectá tu celular al Wi-Fi del módulo para operar el aula en modo local.</p>"
            + "<div class='box'>Red Wi-Fi: <b>ControlAula</b><br>Clave: <b>12345678</b><br>Dirección: <b>" + safeUrl + "</b></div>"
            + "<button onclick='AndroidApp.reintentar()'>Reintentar conexión</button>"
            + "<input id='ipIn' value='" + safeUrl + "' placeholder='http://192.168.4.1/'>"
            + "<button class='sec' onclick='AndroidApp.cambiarUrl(document.getElementById(\"ipIn\").value)'>Guardar dirección y conectar</button>"
            + "</div></body></html>";
        webView.loadDataWithBaseURL("about:blank", html, "text/html", "UTF-8", null);
    }

    private class NativeBridge {
        @JavascriptInterface
        public void reintentar() {
            runOnUiThread(() -> webView.loadUrl(currentBaseUrl));
        }

        @JavascriptInterface
        public void cambiarUrl(String nueva) {
            if (nueva == null) return;
            String limpia = nueva.trim();
            if (limpia.length() == 0) limpia = DEFAULT_URL;
            if (!limpia.startsWith("http://") && !limpia.startsWith("https://")) {
                limpia = "http://" + limpia;
            }
            if (!limpia.endsWith("/")) limpia += "/";
            final String urlFinal = limpia;
            currentBaseUrl = urlFinal;
            getSharedPreferences(PREFS_NAME, MODE_PRIVATE).edit().putString(KEY_URL, urlFinal).apply();
            runOnUiThread(() -> webView.loadUrl(urlFinal));
        }
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        if (keyCode == KeyEvent.KEYCODE_BACK && webView != null && webView.canGoBack()) {
            webView.goBack();
            return true;
        }
        return super.onKeyDown(keyCode, event);
    }

    @Override
    protected void onPause() {
        super.onPause();
        CookieManager.getInstance().flush();
    }
}
