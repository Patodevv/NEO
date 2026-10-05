package com.neomind.remote;

import android.app.Activity;
import android.app.AlertDialog;
import android.app.DownloadManager;
import android.content.ActivityNotFoundException;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Bitmap;
import android.graphics.Color;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.text.InputType;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.view.inputmethod.InputMethodManager;
import android.webkit.CookieManager;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

import java.net.URI;

public final class MainActivity extends Activity {
    private static final String ACTION_CONFIGURE = "com.neomind.remote.CONFIGURE";
    private static final String PREFS_NAME = "connection";
    private static final String PREF_SERVER = "server";
    private static final String DEFAULT_PATH = "/neo/neo-20260823T183325Z-1-001/neo/";
    private static final int FILE_CHOOSER_REQUEST = 2001;

    private SharedPreferences preferences;
    private WebView webView;
    private ProgressBar progressBar;
    private Button ipButton;
    private LinearLayout errorPanel;
    private TextView errorTitle;
    private TextView errorMessage;
    private AlertDialog serverDialog;
    private ValueCallback<Uri[]> fileChooserCallback;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        getWindow().setStatusBarColor(Color.rgb(7, 17, 38));
        getWindow().setNavigationBarColor(Color.rgb(7, 17, 38));
        preferences = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);

        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(Color.rgb(7, 17, 38));

        webView = new WebView(this);
        root.addView(webView, new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT));

        progressBar = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        progressBar.setMax(100);
        FrameLayout.LayoutParams progressParams = new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                dp(3));
        progressParams.gravity = Gravity.TOP;
        root.addView(progressBar, progressParams);

        createErrorPanel(root);

        ipButton = new Button(this);
        ipButton.setText("IP");
        ipButton.setTextSize(13);
        ipButton.setTextColor(Color.WHITE);
        ipButton.setAllCaps(false);
        ipButton.setContentDescription("Trocar o IP do computador");
        ipButton.setPadding(dp(8), 0, dp(8), 0);
        ipButton.setMinWidth(0);
        ipButton.setMinHeight(0);
        ipButton.setElevation(dp(9));
        ipButton.setBackground(roundedBackground(Color.rgb(82, 109, 219), 24));
        ipButton.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                showServerDialog(false);
            }
        });

        FrameLayout.LayoutParams buttonParams = new FrameLayout.LayoutParams(dp(58), dp(46));
        buttonParams.gravity = Gravity.TOP | Gravity.END;
        buttonParams.setMargins(dp(12), dp(12), dp(12), dp(12));
        root.addView(ipButton, buttonParams);

        setContentView(root);
        configureWebView();

        if (savedInstanceState != null) {
            webView.restoreState(savedInstanceState);
        }

        String server = preferences.getString(PREF_SERVER, "");
        boolean requestedConfiguration = ACTION_CONFIGURE.equals(getIntent().getAction());
        if (requestedConfiguration || server == null || server.trim().isEmpty()) {
            final boolean required = server == null || server.trim().isEmpty();
            root.post(new Runnable() {
                @Override
                public void run() {
                    showServerDialog(required);
                }
            });
        } else if (savedInstanceState == null) {
            loadServer(server);
        }
    }

    private void configureWebView() {
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setAllowContentAccess(true);
        settings.setAllowFileAccess(false);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setLoadWithOverviewMode(false);
        settings.setUseWideViewPort(false);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        settings.setUserAgentString(settings.getUserAgentString() + " NEO-Remote/1.1.0");

        CookieManager.getInstance().setAcceptCookie(true);
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true);

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                hideError();
                progressBar.setVisibility(View.VISIBLE);
                progressBar.setProgress(12);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                progressBar.setProgress(100);
                progressBar.setVisibility(View.GONE);
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request.isForMainFrame()) {
                    progressBar.setVisibility(View.GONE);
                    showConnectionError(
                            "Não foi possível conectar",
                            "Confira o Wi-Fi e o IP do computador. Você pode trocar o IP agora pelo botão acima.");
                }
            }

            @Override
            public void onReceivedHttpError(WebView view, WebResourceRequest request, WebResourceResponse response) {
                if (request.isForMainFrame() && response.getStatusCode() >= 400) {
                    progressBar.setVisibility(View.GONE);
                    showConnectionError(
                            "O NEO respondeu com erro " + response.getStatusCode(),
                            "O computador foi encontrado, mas o caminho do NEO pode estar incorreto.");
                }
            }

            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                Uri uri = request.getUrl();
                String scheme = uri.getScheme();
                if ("http".equalsIgnoreCase(scheme) || "https".equalsIgnoreCase(scheme)) {
                    return false;
                }
                return openExternal(uri);
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int progress) {
                progressBar.setProgress(progress);
                progressBar.setVisibility(progress >= 100 ? View.GONE : View.VISIBLE);
            }

            @Override
            public boolean onShowFileChooser(
                    WebView view,
                    ValueCallback<Uri[]> callback,
                    FileChooserParams params) {
                if (fileChooserCallback != null) {
                    fileChooserCallback.onReceiveValue(null);
                }
                fileChooserCallback = callback;
                try {
                    startActivityForResult(params.createIntent(), FILE_CHOOSER_REQUEST);
                    return true;
                } catch (ActivityNotFoundException exception) {
                    fileChooserCallback = null;
                    Toast.makeText(MainActivity.this,
                            "Nenhum seletor de arquivos está disponível.",
                            Toast.LENGTH_LONG).show();
                    return false;
                }
            }
        });

        webView.setDownloadListener(new android.webkit.DownloadListener() {
            @Override
            public void onDownloadStart(
                    String url,
                    String userAgent,
                    String contentDisposition,
                    String mimeType,
                    long contentLength) {
                startDownload(url, userAgent, contentDisposition, mimeType);
            }
        });
    }

    private void createErrorPanel(FrameLayout root) {
        errorPanel = new LinearLayout(this);
        errorPanel.setOrientation(LinearLayout.VERTICAL);
        errorPanel.setGravity(Gravity.CENTER);
        errorPanel.setPadding(dp(28), dp(28), dp(28), dp(28));
        errorPanel.setBackgroundColor(Color.rgb(7, 17, 38));
        errorPanel.setVisibility(View.GONE);

        errorTitle = new TextView(this);
        errorTitle.setTextColor(Color.WHITE);
        errorTitle.setTextSize(23);
        errorTitle.setGravity(Gravity.CENTER);
        errorPanel.addView(errorTitle, matchWrap());

        errorMessage = new TextView(this);
        errorMessage.setTextColor(Color.rgb(198, 210, 239));
        errorMessage.setTextSize(16);
        errorMessage.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams messageParams = matchWrap();
        messageParams.setMargins(0, dp(14), 0, dp(24));
        errorPanel.addView(errorMessage, messageParams);

        Button changeIp = actionButton("Trocar IP");
        changeIp.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                showServerDialog(false);
            }
        });
        errorPanel.addView(changeIp, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(50)));

        Button retry = actionButton("Tentar novamente");
        retry.setBackground(roundedBackground(Color.rgb(35, 56, 103), 14));
        retry.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                loadServer(preferences.getString(PREF_SERVER, ""));
            }
        });
        LinearLayout.LayoutParams retryParams = new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(50));
        retryParams.setMargins(0, dp(12), 0, 0);
        errorPanel.addView(retry, retryParams);

        FrameLayout.LayoutParams panelParams = new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT);
        root.addView(errorPanel, panelParams);
    }

    private void showServerDialog(boolean required) {
        if (serverDialog != null && serverDialog.isShowing()) {
            return;
        }

        String previous = preferences.getString(PREF_SERVER, "");
        String host = extractHost(previous);
        String path = extractPath(previous);

        LinearLayout content = new LinearLayout(this);
        content.setOrientation(LinearLayout.VERTICAL);
        content.setPadding(dp(24), dp(8), dp(24), dp(4));

        TextView explanation = dialogText(
                "Digite o novo IP do computador. O caminho do NEO será mantido automaticamente.",
                16,
                Color.rgb(35, 45, 70));
        content.addView(explanation, matchWrap());

        EditText input = new EditText(this);
        input.setHint("Ex.: 192.168.0.10");
        input.setSingleLine(true);
        input.setSelectAllOnFocus(true);
        input.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_URI);
        input.setText(host);
        LinearLayout.LayoutParams inputParams = matchWrap();
        inputParams.setMargins(0, dp(12), 0, dp(10));
        content.addView(input, inputParams);

        TextView pathInfo = dialogText(
                "Caminho mantido: " + path + "\nVocê também pode colar o endereço completo neste campo.",
                13,
                Color.rgb(91, 105, 139));
        content.addView(pathInfo, matchWrap());

        ScrollView scroll = new ScrollView(this);
        scroll.addView(content);

        AlertDialog.Builder builder = new AlertDialog.Builder(this)
                .setTitle(required ? "Conectar ao NEO" : "Trocar IP do computador")
                .setView(scroll)
                .setPositiveButton("Conectar", null);

        if (!required) {
            builder.setNegativeButton("Cancelar", null);
        }

        serverDialog = builder.create();
        serverDialog.setCancelable(!required);
        serverDialog.setCanceledOnTouchOutside(!required);
        serverDialog.setOnShowListener(new android.content.DialogInterface.OnShowListener() {
            @Override
            public void onShow(android.content.DialogInterface dialog) {
                Button positive = serverDialog.getButton(AlertDialog.BUTTON_POSITIVE);
                positive.setOnClickListener(new View.OnClickListener() {
                    @Override
                    public void onClick(View view) {
                        try {
                            String normalized = normalizeServer(input.getText().toString(), previous);
                            preferences.edit().putString(PREF_SERVER, normalized).apply();
                            serverDialog.dismiss();
                            loadServer(normalized);
                        } catch (IllegalArgumentException exception) {
                            input.setError(exception.getMessage());
                            input.requestFocus();
                        }
                    }
                });

                input.requestFocus();
                input.setSelection(0, input.length());
                input.postDelayed(new Runnable() {
                    @Override
                    public void run() {
                        InputMethodManager keyboard =
                                (InputMethodManager) getSystemService(INPUT_METHOD_SERVICE);
                        keyboard.showSoftInput(input, InputMethodManager.SHOW_IMPLICIT);
                    }
                }, 180);
            }
        });
        serverDialog.setOnDismissListener(new android.content.DialogInterface.OnDismissListener() {
            @Override
            public void onDismiss(android.content.DialogInterface dialog) {
                serverDialog = null;
            }
        });
        serverDialog.show();
    }

    private String normalizeServer(String value, String previous) {
        String raw = value == null ? "" : value.trim();
        if (raw.isEmpty()) {
            throw new IllegalArgumentException("Informe o IP do computador.");
        }

        String candidate;
        if (raw.contains("://")) {
            candidate = raw;
        } else if (raw.contains("/")) {
            candidate = "http://" + raw;
        } else {
            String scheme = extractScheme(previous);
            candidate = scheme + "://" + raw + extractPath(previous);
        }

        try {
            URI uri = new URI(candidate);
            String scheme = uri.getScheme();
            if (!("http".equalsIgnoreCase(scheme) || "https".equalsIgnoreCase(scheme))) {
                throw new IllegalArgumentException("Use um endereço http:// ou https://.");
            }
            if (uri.getHost() == null || uri.getHost().trim().isEmpty()) {
                throw new IllegalArgumentException("O IP ou endereço informado não é válido.");
            }

            String normalized = uri.toASCIIString();
            if (uri.getPath() == null || uri.getPath().isEmpty()) {
                normalized = normalized + "/";
            }
            return normalized;
        } catch (java.net.URISyntaxException exception) {
            throw new IllegalArgumentException("O IP ou endereço informado não é válido.");
        }
    }

    private String extractHost(String server) {
        if (server == null || server.trim().isEmpty()) {
            return "";
        }
        try {
            URI uri = new URI(server);
            if (uri.getHost() == null) {
                return "";
            }
            return uri.getPort() >= 0 ? uri.getHost() + ":" + uri.getPort() : uri.getHost();
        } catch (Exception ignored) {
            return "";
        }
    }

    private String extractScheme(String server) {
        try {
            URI uri = new URI(server == null ? "" : server);
            if ("https".equalsIgnoreCase(uri.getScheme())) {
                return "https";
            }
        } catch (Exception ignored) {
            // HTTP is the expected local-network default.
        }
        return "http";
    }

    private String extractPath(String server) {
        if (server != null && !server.trim().isEmpty()) {
            try {
                URI uri = new URI(server);
                String path = uri.getRawPath();
                if (path != null && !path.isEmpty() && !"/".equals(path)) {
                    return path.endsWith("/") ? path : path + "/";
                }
            } catch (Exception ignored) {
                // Use the path of this NEO installation below.
            }
        }
        return DEFAULT_PATH;
    }

    private void loadServer(String server) {
        if (server == null || server.trim().isEmpty()) {
            showServerDialog(true);
            return;
        }
        hideError();
        webView.stopLoading();
        webView.loadUrl(server);
    }

    private void showConnectionError(String title, String message) {
        errorTitle.setText(title);
        errorMessage.setText(message);
        errorPanel.setVisibility(View.VISIBLE);
        errorPanel.bringToFront();
        ipButton.bringToFront();
    }

    private void hideError() {
        errorPanel.setVisibility(View.GONE);
    }

    private boolean openExternal(Uri uri) {
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, uri));
            return true;
        } catch (ActivityNotFoundException exception) {
            Toast.makeText(this, "Nenhum aplicativo pode abrir este link.", Toast.LENGTH_LONG).show();
            return true;
        }
    }

    private void startDownload(String url, String userAgent, String disposition, String mimeType) {
        try {
            String filename = URLUtil.guessFileName(url, disposition, mimeType);
            DownloadManager.Request request = new DownloadManager.Request(Uri.parse(url));
            request.setTitle(filename);
            request.setMimeType(mimeType);
            request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            if (userAgent != null) {
                request.addRequestHeader("User-Agent", userAgent);
            }
            String cookie = CookieManager.getInstance().getCookie(url);
            if (cookie != null) {
                request.addRequestHeader("Cookie", cookie);
            }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, filename);
            } else {
                request.setDestinationInExternalFilesDir(this, Environment.DIRECTORY_DOWNLOADS, filename);
            }
            DownloadManager manager = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
            manager.enqueue(request);
            Toast.makeText(this, "Download iniciado.", Toast.LENGTH_SHORT).show();
        } catch (RuntimeException exception) {
            Toast.makeText(this, "Não foi possível iniciar o download.", Toast.LENGTH_LONG).show();
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode == FILE_CHOOSER_REQUEST && fileChooserCallback != null) {
            Uri[] result = WebChromeClient.FileChooserParams.parseResult(resultCode, data);
            fileChooserCallback.onReceiveValue(result);
            fileChooserCallback = null;
        }
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        if (ACTION_CONFIGURE.equals(intent.getAction())) {
            showServerDialog(false);
        }
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        webView.saveState(outState);
        super.onSaveInstanceState(outState);
    }

    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }

    @Override
    protected void onPause() {
        webView.onPause();
        super.onPause();
    }

    @Override
    protected void onResume() {
        super.onResume();
        webView.onResume();
    }

    @Override
    protected void onDestroy() {
        if (fileChooserCallback != null) {
            fileChooserCallback.onReceiveValue(null);
            fileChooserCallback = null;
        }
        if (serverDialog != null) {
            serverDialog.dismiss();
        }
        webView.stopLoading();
        webView.setWebChromeClient(null);
        webView.setWebViewClient(null);
        webView.destroy();
        super.onDestroy();
    }

    private Button actionButton(String text) {
        Button button = new Button(this);
        button.setText(text);
        button.setTextColor(Color.WHITE);
        button.setTextSize(15);
        button.setAllCaps(false);
        button.setBackground(roundedBackground(Color.rgb(82, 109, 219), 14));
        return button;
    }

    private TextView dialogText(String text, int size, int color) {
        TextView view = new TextView(this);
        view.setText(text);
        view.setTextSize(size);
        view.setTextColor(color);
        return view;
    }

    private LinearLayout.LayoutParams matchWrap() {
        return new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT);
    }

    private GradientDrawable roundedBackground(int color, int radiusDp) {
        GradientDrawable background = new GradientDrawable();
        background.setColor(color);
        background.setCornerRadius(dp(radiusDp));
        return background;
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }
}
