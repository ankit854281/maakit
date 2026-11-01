package com.maakit.trial;

import android.app.Activity;
import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;

/**
 * Maakit trial app: the clickable customer-app prototype (app-trial/web)
 * packed in a WebView. Sample shops and prices only — not connected to
 * the live Maakit database.
 */
public class MainActivity extends Activity {
    private WebView web;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        web = new WebView(this);
        WebSettings s = web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setAllowFileAccess(true);
        s.setTextZoom(100);
        web.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest req) {
                return openOutside(req.getUrl());
            }

            @SuppressWarnings("deprecation")
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                return openOutside(Uri.parse(url));
            }
        });
        setContentView(web);
        if (savedInstanceState != null) {
            web.restoreState(savedInstanceState);
        } else {
            web.loadUrl("file:///android_asset/www/index.html");
        }
    }

    /** App pages stay inside; phone calls, WhatsApp and web links open in their own apps. */
    private boolean openOutside(Uri uri) {
        if ("file".equals(uri.getScheme())) return false;
        try {
            String action = "tel".equals(uri.getScheme()) ? Intent.ACTION_DIAL : Intent.ACTION_VIEW;
            startActivity(new Intent(action, uri));
        } catch (Exception ignored) {
        }
        return true;
    }

    @Override
    protected void onSaveInstanceState(Bundle out) {
        super.onSaveInstanceState(out);
        web.saveState(out);
    }

    @Override
    public void onBackPressed() {
        web.evaluateJavascript("(window.MK_BACK && window.MK_BACK()) ? 1 : 0", value -> {
            if (!"1".equals(value)) finish();
        });
    }
}
