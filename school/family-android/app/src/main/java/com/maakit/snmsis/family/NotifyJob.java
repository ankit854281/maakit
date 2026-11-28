package com.maakit.snmsis.family;

import android.Manifest;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.job.JobInfo;
import android.app.job.JobParameters;
import android.app.job.JobScheduler;
import android.app.job.JobService;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.os.Build;
import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import org.json.JSONArray;
import org.json.JSONObject;

/**
 * Family notifications without any third-party service (owner, 28 Nov 2026: "bachcha absent ho, homework nahi kiya — message jaye").
 * Every ~20 minutes (when online) asks https://maakit.in/school/famnotif_api.php for anything newer than the last check and shows it as
 * a phone notification: absent / leave, homework not done, copy incomplete, today's homework, leave decision, notice, school reply.
 * Uses only the login the parent already made in the app (saved by MainActivity.Reports.setSession); logging out stops it.
 */
public class NotifyJob extends JobService {
    static final int JOB_ID = 4711;
    static final String PREFS = "snmsis_notify", CHANNEL = "school";
    private static final String API = "https://maakit.in/school/famnotif_api.php?a=list&since=";

    static void schedule(Context c) {
        JobScheduler js = (JobScheduler) c.getSystemService(Context.JOB_SCHEDULER_SERVICE);
        if (js == null) return;
        JobInfo job = new JobInfo.Builder(JOB_ID, new ComponentName(c, NotifyJob.class))
            .setRequiredNetworkType(JobInfo.NETWORK_TYPE_ANY)
            .setPeriodic(20 * 60 * 1000L)
            .setPersisted(true)
            .build();
        js.schedule(job);
    }

    static void cancel(Context c) {
        JobScheduler js = (JobScheduler) c.getSystemService(Context.JOB_SCHEDULER_SERVICE);
        if (js != null) js.cancel(JOB_ID);
    }

    static void channel(Context c) {
        NotificationManager nm = (NotificationManager) c.getSystemService(Context.NOTIFICATION_SERVICE);
        if (nm == null) return;
        NotificationChannel ch = new NotificationChannel(CHANNEL, "School updates", NotificationManager.IMPORTANCE_DEFAULT);
        ch.setDescription("Attendance, homework checks, leave, notices and replies from the school");
        nm.createNotificationChannel(ch);
    }

    @Override public boolean onStartJob(JobParameters params) {
        new Thread(() -> {
            try { check(getApplicationContext()); } catch (Exception ignored) { }
            jobFinished(params, false);
        }).start();
        return true;
    }

    @Override public boolean onStopJob(JobParameters params) { return true; }

    static void check(Context c) throws Exception {
        SharedPreferences p = c.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
        String token = p.getString("token", "");
        if (token == null || !token.matches("[a-f0-9]{64}")) return;
        long last = p.getLong("last", 0L);
        HttpURLConnection con = (HttpURLConnection) new URL(API + last).openConnection();
        con.setConnectTimeout(15000);
        con.setReadTimeout(20000);
        con.setRequestProperty("X-School-Token", token);
        int code = con.getResponseCode();
        if (code == 401) { p.edit().remove("token").apply(); cancel(c); return; }
        if (code != 200) return;
        String body;
        try (InputStream in = con.getInputStream(); ByteArrayOutputStream out = new ByteArrayOutputStream()) {
            byte[] b = new byte[8192]; int n;
            while ((n = in.read(b)) != -1) { out.write(b, 0, n); if (out.size() > 2_000_000) return; }
            body = out.toString("UTF-8");
        }
        JSONObject j = new JSONObject(body);
        if (!j.optBoolean("ok")) return;
        long now = j.optLong("now", System.currentTimeMillis() / 1000);
        JSONArray items = j.optJSONArray("items");
        p.edit().putLong("last", now).apply();
        if (last == 0L || items == null || items.length() == 0) return;     // first check only sets the starting point
        if (Build.VERSION.SDK_INT >= 33 && c.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) return;
        NotificationManager nm = (NotificationManager) c.getSystemService(Context.NOTIFICATION_SERVICE);
        if (nm == null) return;
        channel(c);
        int shown = 0;
        for (int i = 0; i < items.length() && shown < 5; i++, shown++) {
            JSONObject x = items.getJSONObject(i);
            post(c, nm, x.optString("id").hashCode(), x.optString("title"), x.optString("body"), x.optString("go", "pnotif"));
        }
        if (items.length() > 5) post(c, nm, JOB_ID, (items.length() - 5) + " more school updates", "Open the app to see all notifications.", "pnotif");
    }

    private static void post(Context c, NotificationManager nm, int id, String title, String text, String go) {
        Intent open = new Intent(c, MainActivity.class).putExtra("go", go).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        PendingIntent pi = PendingIntent.getActivity(c, id, open, PendingIntent.FLAG_IMMUTABLE | PendingIntent.FLAG_UPDATE_CURRENT);
        Notification n = new Notification.Builder(c, CHANNEL)
            .setSmallIcon(R.drawable.app_icon)
            .setContentTitle(title)
            .setContentText(text)
            .setStyle(new Notification.BigTextStyle().bigText(text))
            .setContentIntent(pi)
            .setAutoCancel(true)
            .build();
        nm.notify(id, n);
    }
}
