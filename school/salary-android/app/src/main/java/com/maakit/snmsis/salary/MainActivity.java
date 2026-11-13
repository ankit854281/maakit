package com.maakit.snmsis.salary;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.app.AlertDialog;
import android.content.Intent;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.print.PrintAttributes;
import android.print.PrintManager;
import android.util.Base64;
import android.view.Gravity;
import android.view.View;
import android.webkit.*;
import android.widget.*;
import java.io.ByteArrayInputStream;
import java.io.OutputStream;

public class MainActivity extends Activity {
    private static final String HOME = "https://maakit.in/school/manager.php";
    private WebView web;
    private ProgressBar progress;
    private LinearLayout error;
    private byte[] report;
    private boolean failed;
    private WebView printView;           // page being printed / saved as PDF (owner, 13 Nov 2026)
    private ValueCallback<Uri[]> pick;   // CSV upload (Staff attendance) — Android's own file picker

    private static boolean trusted(Uri uri) {
        return uri != null && "https".equals(uri.getScheme())
            && "maakit.in".equals(uri.getHost()) && (uri.getPort() == -1 || uri.getPort() == 443)
            && uri.getUserInfo() == null && uri.getPath() != null && uri.getPath().startsWith("/school/");
    }

    @SuppressLint("SetJavaScriptEnabled")
    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        getWindow().setStatusBarColor(Color.rgb(15,81,50));
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
        message.setText("SNMSIS Salary\n\nCould not open the school app.\nCheck your internet connection and try again.");
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
        web.setWebChromeClient(new WebChromeClient() {
            @Override public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
                if (pick != null) pick.onReceiveValue(null);
                pick = callback;
                Intent intent = new Intent(Intent.ACTION_GET_CONTENT);
                intent.addCategory(Intent.CATEGORY_OPENABLE);
                intent.setType("*/*");
                try { startActivityForResult(Intent.createChooser(intent, "Choose the CSV file"), 101); }
                catch (android.content.ActivityNotFoundException e) { pick = null; toast("No app is available to choose a file."); return false; }
                return true;
            }
        });
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
                if (trusted(request.getUrl())) return null;
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
        // Owner, 13 Nov 2026: "print ke baad save as PDF ka option nahi" — lists (tour, receipts) open Android's own
        // print screen, where "Save as PDF" is a printer. The page is rendered with JavaScript off and no network.
        @JavascriptInterface public void printHtml(String title, String html) {
            if (html == null || html.length() > 3_000_000) return;
            runOnUiThread(() -> {
                if (!trusted(Uri.parse(web.getUrl() == null ? "" : web.getUrl()))) return;
                final String job = (title == null || title.trim().isEmpty() ? "SNMSIS" : title.trim()).replaceAll("[^a-zA-Z0-9 ._-]", " ").trim();
                WebView pv = new WebView(MainActivity.this);
                pv.getSettings().setJavaScriptEnabled(false);
                pv.getSettings().setAllowFileAccess(false);
                pv.getSettings().setAllowContentAccess(false);
                pv.getSettings().setBlockNetworkLoads(true);
                pv.setWebViewClient(new WebViewClient() {
                    @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) { return true; }
                    @Override public void onPageFinished(WebView view, String url) {
                        PrintManager pm = (PrintManager) getSystemService(PRINT_SERVICE);
                        if (pm == null) { toast("Printing is not available on this phone."); return; }
                        try { pm.print(job.isEmpty() ? "SNMSIS" : job, view.createPrintDocumentAdapter(job.isEmpty() ? "SNMSIS" : job), new PrintAttributes.Builder().setMediaSize(PrintAttributes.MediaSize.ISO_A4).build()); }
                        catch (RuntimeException e) { toast("Could not open the print screen."); }
                    }
                });
                printView = pv;
                pv.loadDataWithBaseURL("https://maakit.in/school/", html, "text/html", "UTF-8", null);
            });
        }
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
        if (requestCode == 101) {
            if (pick != null) pick.onReceiveValue(resultCode == RESULT_OK && data != null && data.getData() != null ? new Uri[]{ data.getData() } : null);
            pick = null;
            return;
        }
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
        else new AlertDialog.Builder(this).setMessage("Close SNMSIS Salary?")
            .setPositiveButton("Close",(d,w) -> finish()).setNegativeButton("Stay",null).show();
    }
    @Override protected void onDestroy() {
        web.removeJavascriptInterface("SNMSISFiles");
        web.destroy();
        report = null;
        super.onDestroy();
    }
}
