package com.maakit.snmsis.teacher;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.app.AlertDialog;
import android.content.Intent;
import android.content.ClipData;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.os.Parcelable;
import android.provider.MediaStore;
import android.speech.RecognizerIntent;
import android.speech.tts.TextToSpeech;
import android.util.Base64;
import android.view.Gravity;
import android.view.View;
import android.webkit.*;
import android.widget.*;
import java.io.ByteArrayInputStream;
import java.io.File;
import java.io.IOException;
import androidx.core.content.FileProvider;
import androidx.webkit.JavaScriptReplyProxy;
import androidx.webkit.WebViewCompat;
import androidx.webkit.WebViewFeature;
import java.util.ArrayList;
import java.util.Locale;
import org.json.JSONException;
import org.json.JSONObject;
import java.io.OutputStream;

public class MainActivity extends Activity {
    private static final String HOME = "https://maakit.in/school/teacher.php";
    private WebView web;
    private ProgressBar progress;
    private LinearLayout error;
    private byte[] report;
    private boolean failed;
    private ValueCallback<Uri[]> photoCallback;
    private Uri cameraPhoto;
    private static final int VOICE_REQUEST = 102;
    private TextToSpeech speech;
    private boolean speechReady;
    private JavaScriptReplyProxy voiceReply;
    private int voiceId;

    private static boolean trusted(Uri uri) {
        return uri != null && "https".equals(uri.getScheme())
            && "maakit.in".equals(uri.getHost()) && (uri.getPort() == -1 || uri.getPort() == 443)
            && uri.getUserInfo() == null && uri.getPath() != null && uri.getPath().startsWith("/school/");
    }

    @SuppressLint("SetJavaScriptEnabled")
    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        getWindow().setStatusBarColor(Color.rgb(15,118,110));
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
        message.setText("SNMSIS Teacher\n\nCould not open the school app.\nCheck your internet connection and try again.");
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
        attachVoice();

        // The WebView may not read local files itself. Android's document picker and
        // camera hand a scoped content Uri back to the HTML file input instead.
        web.setWebChromeClient(new WebChromeClient() {
            @Override public boolean onShowFileChooser(WebView view,
                    ValueCallback<Uri[]> callback, FileChooserParams params) {
                if (photoCallback != null) photoCallback.onReceiveValue(null);
                photoCallback = callback;
                cameraPhoto = null;
                Intent gallery = new Intent(Intent.ACTION_GET_CONTENT);
                gallery.addCategory(Intent.CATEGORY_OPENABLE);
                gallery.setType("image/*");
                gallery.putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true);
                Intent chooser = Intent.createChooser(gallery, "Choose chapter photos");
                try {
                    File dir = new File(getCacheDir(), "camera");
                    if (!dir.exists() && !dir.mkdirs()) throw new IOException("Cannot create camera cache");
                    File photo = File.createTempFile("snmsis-", ".jpg", dir);
                    cameraPhoto = FileProvider.getUriForFile(MainActivity.this,
                        getPackageName() + ".files", photo);
                    Intent camera = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
                    camera.putExtra(MediaStore.EXTRA_OUTPUT, cameraPhoto);
                    camera.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_GRANT_WRITE_URI_PERMISSION);
                    camera.setClipData(ClipData.newUri(getContentResolver(), "Chapter photo", cameraPhoto));
                    if (camera.resolveActivity(getPackageManager()) != null) {
                        chooser.putExtra(Intent.EXTRA_INITIAL_INTENTS, new Parcelable[]{camera});
                    }
                } catch (Exception e) {
                    // Still let the teacher select an existing image.
                    cameraPhoto = null;
                }
                try {
                    startActivityForResult(chooser, 101);
                } catch (android.content.ActivityNotFoundException e) {
                    photoCallback.onReceiveValue(null);
                    photoCallback = null;
                    cameraPhoto = null;
                    toast("No photo app is available.");
                }
                return true;
            }
        });
        web.setWebViewClient(new WebViewClient() {
            @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                Uri uri = request.getUrl();
                if (trusted(uri)) return false;
                if (!request.isForMainFrame()) return true;
                String scheme = uri.getScheme();
                if ("tel".equals(scheme) || "mailto".equals(scheme)) {
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
                if (!trusted(Uri.parse(url))) {
                    view.stopLoading();
                    showError();
                    return;
                }
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

    private boolean teacherPage(String url) {
        Uri uri = Uri.parse(url == null ? "" : url);
        return trusted(uri) && "/school/teacher.php".equals(uri.getPath());
    }

    private void attachVoice() {
        if (!WebViewFeature.isFeatureSupported(WebViewFeature.WEB_MESSAGE_LISTENER)) return;
        speech = new TextToSpeech(this, status -> speechReady = status == TextToSpeech.SUCCESS);
        WebViewCompat.addWebMessageListener(web, "SNMSISTeacherVoice",
            java.util.Collections.singleton("https://maakit.in"), (view, message, origin, isMainFrame, reply) -> {
                if (!isMainFrame || !teacherPage(view.getUrl())) return;
                String data = message.getData();
                if (data == null || data.length() > 2048) return;
                try {
                    JSONObject request = new JSONObject(data);
                    String locale = request.optString("lang");
                    if (!locale.equals("hi-IN") && !locale.equals("en-IN")) return;
                    if ("speak".equals(request.optString("action"))) {
                        String text = request.optString("text");
                        if (!text.isEmpty() && text.length() <= 400) speakReply(text, locale);
                    } else if ("listen".equals(request.optString("action")) && request.optInt("id") > 0) {
                        startVoice(request.optInt("id"), locale, reply);
                    }
                } catch (JSONException ignored) {
                    // Ignore messages that do not use the voice bridge protocol.
                }
            });
    }

    private void speakReply(String text, String locale) {
        if (!speechReady || speech == null
                || speech.setLanguage(Locale.forLanguageTag(locale)) < TextToSpeech.LANG_AVAILABLE
                || speech.speak(text, TextToSpeech.QUEUE_FLUSH, null, "teacher-reply") == TextToSpeech.ERROR) {
            toast(text);
        }
    }

    private void startVoice(int id, String locale, JavaScriptReplyProxy reply) {
        if (voiceReply != null) {
            voiceRespond(reply, id, "", "Finish the current voice request and try again.");
            return;
        }
        if (speech != null) speech.stop();
        Intent intent = new Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH);
        intent.putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM);
        intent.putExtra(RecognizerIntent.EXTRA_LANGUAGE, locale);
        intent.putExtra(RecognizerIntent.EXTRA_MAX_RESULTS, 1);
        intent.putExtra(RecognizerIntent.EXTRA_PROMPT, locale.equals("hi-IN") ? "अपना कमांड बोलें" : "Speak a command");
        try {
            voiceReply = reply;
            voiceId = id;
            startActivityForResult(intent, VOICE_REQUEST);
        } catch (android.content.ActivityNotFoundException e) {
            voiceReply = null;
            voiceRespond(reply, id, "", "Voice recognition is unavailable on this phone. Type the command instead.");
        }
    }

    private void voiceRespond(JavaScriptReplyProxy reply, int id, String text, String error) {
        if (reply == null || !teacherPage(web.getUrl())) return;
        try {
            JSONObject result = new JSONObject().put("id", id).put("text", text).put("error", error);
            reply.postMessage(result.toString());
        } catch (JSONException | IllegalStateException ignored) {
            // The page may have closed while recognition was active.
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
        // Certificates (owner, 6 Nov 2026): a PNG drawn by the school page is shared through Android's share sheet
        // (WhatsApp, Drive, Gallery…). Only school-origin pages; the file goes to this app's own cache folder.
        @JavascriptInterface public void shareImage(String filename, String encoded) {
            if (encoded == null || encoded.length() > 9_000_000) return;
            runOnUiThread(() -> {
                if (!trusted(Uri.parse(web.getUrl() == null ? "" : web.getUrl()))) return;
                try {
                    byte[] bytes = Base64.decode(encoded, Base64.DEFAULT);
                    if (bytes.length > 6_291_456) { toast("This picture is too large."); return; }
                    String safe = (filename == null ? "certificate.png" : filename).replaceAll("[^a-zA-Z0-9._-]", "_");
                    if (!safe.endsWith(".png")) safe += ".png";
                    if (safe.length() > 90) safe = "certificate.png";
                    File dir = new File(getCacheDir(), "share");
                    if (!dir.exists() && !dir.mkdirs()) throw new IOException("Cannot create share cache");
                    File f = new File(dir, safe);
                    try (java.io.FileOutputStream out = new java.io.FileOutputStream(f)) { out.write(bytes); }
                    Uri uri = FileProvider.getUriForFile(MainActivity.this, getPackageName() + ".files", f);
                    Intent send = new Intent(Intent.ACTION_SEND);
                    send.setType("image/png");
                    send.putExtra(Intent.EXTRA_STREAM, uri);
                    send.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
                    startActivity(Intent.createChooser(send, "Share certificate"));
                } catch (IOException | IllegalArgumentException | android.content.ActivityNotFoundException e) {
                    toast("Could not share the picture.");
                }
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
        if (requestCode == VOICE_REQUEST) {
            JavaScriptReplyProxy reply = voiceReply;
            voiceReply = null;
            ArrayList<String> matches = resultCode == RESULT_OK && data != null
                ? data.getStringArrayListExtra(RecognizerIntent.EXTRA_RESULTS) : null;
            String text = matches == null || matches.isEmpty() || matches.get(0) == null ? "" : matches.get(0);
            voiceRespond(reply, voiceId, text.substring(0, Math.min(text.length(), 150)),
                text.isEmpty() ? "No speech detected. Try again or type the command." : "");
            return;
        }
        if (requestCode == 101) {
            ValueCallback<Uri[]> callback = photoCallback;
            photoCallback = null;
            Uri savedCameraUri = cameraPhoto;
            cameraPhoto = null;
            if (callback == null) return;
            Uri[] selected = null;
            if (resultCode == RESULT_OK) {
                if (data != null && data.getClipData() != null) {
                    ClipData clip = data.getClipData();
                    int count = Math.min(clip.getItemCount(), 30);
                    if (count > 0) {
                        selected = new Uri[count];
                        for (int i = 0; i < count; i++) selected[i] = clip.getItemAt(i).getUri();
                    }
                } else if (data != null && data.getData() != null) {
                    selected = new Uri[]{data.getData()};
                } else if (savedCameraUri != null) {
                    selected = new Uri[]{savedCameraUri};
                }
            }
            // Cancellation MUST release the callback, or WebView file inputs stop working.
            callback.onReceiveValue(selected);
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
        else new AlertDialog.Builder(this).setMessage("Close SNMSIS Teacher?")
            .setPositiveButton("Close",(d,w) -> finish()).setNegativeButton("Stay",null).show();
    }
    @Override protected void onDestroy() {
        if (photoCallback != null) { photoCallback.onReceiveValue(null); photoCallback = null; }
        voiceReply = null;
        if (speech != null) { speech.shutdown(); speech = null; }
        web.removeJavascriptInterface("SNMSISFiles");
        web.destroy();
        report = null;
        super.onDestroy();
    }
}
