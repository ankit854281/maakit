package com.maakit.snmsis.family;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.app.AlertDialog;
import android.content.Intent;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.util.Base64;
import android.view.Gravity;
import android.view.View;
import android.webkit.*;
import android.widget.*;
import java.io.ByteArrayInputStream;
import java.io.OutputStream;

public class MainActivity extends Activity {
    private static final String HOME = "https://maakit.in/school/";
    private WebView web;
    private ProgressBar progress;
    private LinearLayout error;
    private byte[] report;
    private boolean failed;

    private static boolean trusted(Uri uri) {
        return uri != null && "https".equals(uri.getScheme())
            && "maakit.in".equals(uri.getHost()) && (uri.getPort() == -1 || uri.getPort() == 443)
            && uri.getUserInfo() == null && uri.getPath() != null && uri.getPath().startsWith("/school/");
    }

    private static boolean safeAsset(Uri u) {
        if ("data".equals(u.getScheme())) return u.toString().startsWith("data:image/");
        if (!"https".equals(u.getScheme())) return false;
        String h=u.getHost(), p=u.getPath();
        return "fonts.googleapis.com".equals(h) || ("fonts.gstatic.com".equals(h) && p!=null && p.startsWith("/s/")) || ("cdnjs.cloudflare.com".equals(h) && p!=null && p.startsWith("/ajax/libs/font-awesome/6.5.1/"));
    }

    @SuppressLint("SetJavaScriptEnabled")
    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        getWindow().setStatusBarColor(Color.rgb(67,56,202));
        getWindow().setNavigationBarColor(Color.rgb(247,247,249));
        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setBackgroundColor(Color.rgb(247,247,249));
        progress = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        root.addView(progress, new LinearLayout.LayoutParams(-1, 6));
        error = new LinearLayout(this);
        error.setOrientation(LinearLayout.VERTICAL);
        error.setGravity(Gravity.CENTER);
        error.setPadding(32,48,32,48);
        TextView message = new TextView(this);
        message.setText("SNMSIS Family\n\nCould not open the school app.\nCheck your internet connection and try again.");
        message.setGravity(Gravity.CENTER);
        message.setTextSize(18);
        error.addView(message);
        Button retry = new Button(this);
        retry.setText("Try again");
        retry.setOnClickListener(v -> web.loadUrl(HOME));
        error.addView(retry);
        error.setVisibility(View.GONE);
        root.addView(error, new LinearLayout.LayoutParams(-1,-1));
        web = new WebView(this);
        root.addView(web,new LinearLayout.LayoutParams(-1,0,1));
        setContentView(root);
        WebSettings settings = web.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setAllowFileAccess(false);
        settings.setAllowContentAccess(false);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        settings.setSafeBrowsingEnabled(true);
        settings.setJavaScriptCanOpenWindowsAutomatically(false);
        WebView.setWebContentsDebuggingEnabled(false);
        web.addJavascriptInterface(new Reports(), "SNMSISFiles");
        web.setWebViewClient(new WebViewClient() {
            @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                Uri uri = request.getUrl();
                if (trusted(uri)) return false;
                if (!request.isForMainFrame()) return true;
                String scheme = uri.getScheme();
                if ("https".equals(scheme) || "tel".equals(scheme) || "mailto".equals(scheme)) {
                    try { startActivity(new Intent("tel".equals(scheme) ? Intent.ACTION_DIAL : Intent.ACTION_VIEW, uri)); }
                    catch (android.content.ActivityNotFoundException e) { toast("No app is available to open this link."); }
                }
                return true;
            }
            @Override public WebResourceResponse shouldInterceptRequest(WebView view, WebResourceRequest request) {
                if (trusted(request.getUrl()) || (!request.isForMainFrame() && safeAsset(request.getUrl()))) return null;
                return new WebResourceResponse("text/plain","UTF-8",new ByteArrayInputStream(new byte[0]));
            }
            @Override public void onPageStarted(WebView view, String url, android.graphics.Bitmap icon) {
                failed = false;
                error.setVisibility(View.GONE);
                web.setVisibility(View.VISIBLE);
                progress.setVisibility(View.VISIBLE);
            }
            @Override public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError reason) {
                if (request.isForMainFrame()) showError();
            }
            @Override public void onReceivedHttpError(WebView view, WebResourceRequest request, WebResourceResponse response) {
                if (request.isForMainFrame() && response.getStatusCode() >= 400) showError();
            }
            @Override public void onPageFinished(WebView view, String url) {
                progress.setVisibility(View.GONE);
                if (!failed && trusted(Uri.parse(url))) installFamilyInterface(view);
                if (!failed && trusted(Uri.parse(url))) {
                    view.evaluateJavascript("(function(){if(window.__snFiles)return;window.__snFiles=true;"
                        + "document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a[download]');"
                        + "if(!a||!a.href.startsWith('blob:'))return;e.preventDefault();e.stopImmediatePropagation();"
                        + "fetch(a.href).then(function(r){return r.blob();}).then(function(b){if(b.size>5242880)throw new Error('Too large');"
                        + "var r=new FileReader();r.onload=function(){SNMSISFiles.saveCsv(a.download||'SNMSIS-report.csv',String(r.result).split(',')[1]);};"
                        + "r.readAsDataURL(b);}).catch(function(){alert('Report could not be saved. Please try again.');});"
                        + "},true);})();", null);
                }
            }
        });
        if (state == null || web.restoreState(state) == null) web.loadUrl(HOME);
    }


    private void installFamilyInterface(WebView view) {
        for (String file : new String[]{"language.js", "family-style.js", "family-ui.js"}) {
            try (java.io.InputStream in = getAssets().open(file); java.io.ByteArrayOutputStream out = new java.io.ByteArrayOutputStream()) {
                byte[] buffer = new byte[4096]; int n;
                while ((n = in.read(buffer)) != -1) out.write(buffer, 0, n);
                String script = out.toString("UTF-8");
                if (file.equals("language.js")) script = "if(!window.SchoolLang){" + script + "}";
                view.evaluateJavascript(script, null);
            } catch (java.io.IOException e) { toast("Could not prepare the family screen. Please reopen the app."); }
        }
    }

    private void showError() {
        failed = true;
        progress.setVisibility(View.GONE);
        web.setVisibility(View.GONE);
        error.setVisibility(View.VISIBLE);
    }
    private void toast(String text) { Toast.makeText(this,text,Toast.LENGTH_LONG).show(); }

    // The native save bridge is only exposed to school-origin pages; Android's
    // document picker controls the destination. It never accepts a filesystem path.
    public final class Reports {
        @JavascriptInterface public void saveCsv(String filename, String encoded) {
            if (encoded == null || encoded.length() > 7_000_000) return;
            runOnUiThread(() -> {
                if (!trusted(Uri.parse(web.getUrl() == null ? "" : web.getUrl()))) return;
                if (report != null) { toast("Finish saving the previous report first."); return; }
                try {
                    byte[] bytes = Base64.decode(encoded,Base64.DEFAULT);
                    if (bytes.length > 5_242_880) { toast("This report is too large."); return; }
                    String safe = (filename == null ? "SNMSIS-report.csv" : filename).replaceAll("[^a-zA-Z0-9._-]","_");
                    if (!safe.endsWith(".csv")) safe += ".csv";
                    if (safe.length() > 100) safe = "SNMSIS-report.csv";
                    report = bytes;
                    Intent intent = new Intent(Intent.ACTION_CREATE_DOCUMENT);
                    intent.addCategory(Intent.CATEGORY_OPENABLE);
                    intent.setType("text/csv");
                    intent.putExtra(Intent.EXTRA_TITLE,safe);
                    startActivityForResult(intent,100);
                } catch (IllegalArgumentException | android.content.ActivityNotFoundException e) {
                    report = null;
                    toast("Could not open the save dialog.");
                }
            });
        }
    }
    @Override protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode,resultCode,data);
        if (requestCode != 100) return;
        byte[] pending = report;
        report = null;
        if (resultCode != RESULT_OK || data == null || data.getData() == null || pending == null) return;
        try (OutputStream out = getContentResolver().openOutputStream(data.getData())) {
            if (out == null) throw new java.io.IOException("No output");
            out.write(pending);
            toast("Report saved.");
        } catch (java.io.IOException e) { toast("Report could not be saved. Please try again."); }
    }
    @Override protected void onSaveInstanceState(Bundle out) {
        web.saveState(out);
        super.onSaveInstanceState(out);
    }
    @Override public void onBackPressed() {
        if (web.canGoBack()) web.goBack();
        else new AlertDialog.Builder(this).setMessage("Close SNMSIS Family?")
            .setPositiveButton("Close",(d,w) -> finish()).setNegativeButton("Stay",null).show();
    }
    @Override protected void onDestroy() {
        web.removeJavascriptInterface("SNMSISFiles");
        web.destroy();
        report = null;
        super.onDestroy();
    }
}
