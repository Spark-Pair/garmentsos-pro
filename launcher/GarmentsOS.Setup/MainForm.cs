using System.Diagnostics;
using System.Drawing.Drawing2D;
using System.IO.Compression;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

namespace GarmentsOS.Setup;

internal enum LauncherMode
{
    Manual,
    Install,
    Open,
    Update,
}

internal enum LauncherStepState
{
    Pending,
    Current,
    Done,
    Failed,
}

internal enum FailureActionMode
{
    General,
    Docker,
}

internal sealed class LauncherStep
{
    public string Key { get; init; } = "";
    public string Label { get; init; } = "";
    public LauncherStepState State { get; set; } = LauncherStepState.Pending;
}

internal sealed class DownloadFailedException : Exception
{
    public DownloadFailedException(Exception innerException)
        : base(innerException is InvalidOperationException
            ? innerException.Message
            : "Download failed. Please check your internet connection and try again.", innerException)
    {
    }
}

public sealed class MainForm : Form
{
    private const string DefaultInstallDir = @"C:\SparkPair\GarmentsOS";
    private const string AppUrl = "http://localhost:8000";
    private const string DefaultFeedUrl = "https://www.sparkpair.dev/api/updates/garmentsos-pro/stable/latest.json";
    private const string PrimaryLauncherExeName = "GarmentsOS-PRO.exe";
    private const string UpdateMutexName = @"Local\GarmentsOS_PRO_Update";
    private const string OpenMutexName = @"Local\GarmentsOS_PRO_Launcher_Open";
    private static readonly TimeSpan ActiveUpdateTtl = TimeSpan.FromMinutes(60);

    private static readonly Color BrandBlue = Color.FromArgb(37, 99, 235);
    private static readonly Color AppBackground = Color.FromArgb(238, 241, 244);
    private static readonly Color CardBorder = Color.FromArgb(185, 197, 212);
    private static readonly Color SoftBorder = Color.FromArgb(199, 208, 220);
    private static readonly Color TextPrimary = Color.FromArgb(15, 23, 42);
    private static readonly Color TextMuted = Color.FromArgb(75, 91, 112);
    private static readonly Color TextHint = Color.FromArgb(122, 135, 150);
    private static readonly Color SurfaceSoft = Color.FromArgb(248, 250, 252);


    private readonly HttpClient http = new() { Timeout = TimeSpan.FromSeconds(30) };
    private readonly HttpClient downloadHttp = new() { Timeout = TimeSpan.FromMinutes(30) };
    private readonly JsonSerializerOptions jsonOptions = new() { PropertyNameCaseInsensitive = true };

    private readonly TextBox installDirBox = new() { Text = DefaultInstallDir };
    private readonly TextBox feedUrlBox = new();
    private readonly Label titleLabel = new()
    {
        Text = "GarmentsOS PRO Launcher / Setup",
        Font = new Font(SystemFonts.DefaultFont.FontFamily, 14, FontStyle.Bold),
        AutoSize = true,
    };
    private readonly Label installedVersionLabel = new() { Text = "Installed version: unknown", AutoSize = true };
    private readonly Label appStatusLabel = new() { Text = "App status: unknown", AutoSize = true };
    private readonly Label dockerStatusLabel = new() { Text = "Docker status: unknown", AutoSize = true };
    private readonly Label latestVersionLabel = new() { Text = "Latest version: not checked", AutoSize = true };
    private readonly Label mandatoryLabel = new() { Text = "Mandatory: false", AutoSize = true };
    private readonly Label progressStatusLabel = new() { Text = "Opening GarmentsOS PRO...", AutoSize = true };
    private readonly Label progressPercentLabel = new() { Text = "0%", AutoSize = true };
    private readonly Label installedFooterLabel = new() { Text = "Installed version: checking...", AutoSize = true };
    private readonly ProgressBar progressBar = new() { Style = ProgressBarStyle.Marquee, MarqueeAnimationSpeed = 35, Dock = DockStyle.Top };
    private readonly TextBox notesBox = new() { Multiline = true, ReadOnly = true, ScrollBars = ScrollBars.Vertical };
    private readonly TextBox logBox = new() { Multiline = true, ReadOnly = true, ScrollBars = ScrollBars.Vertical };
    private readonly Button updateButton = new() { Text = "Update Now", Enabled = false };
    private readonly Button detailsButton = new() { Text = "Details", AutoSize = true };
    private readonly FlowLayoutPanel buttonsPanel = new() { Dock = DockStyle.Top, AutoSize = true, WrapContents = true };
    private readonly FlowLayoutPanel failureButtonsPanel = new()
    {
        Dock = DockStyle.None,
        AutoSize = true,
        WrapContents = false,
        Visible = false,
    };
    private readonly TableLayoutPanel pathsPanel = new() { Dock = DockStyle.Top, ColumnCount = 2, AutoSize = true };
    private readonly TableLayoutPanel statusPanel = new() { Dock = DockStyle.Fill, ColumnCount = 2, RowCount = 4 };
    private readonly Panel progressPanel = new() { Dock = DockStyle.Top, Visible = false, Padding = new Padding(0, 10, 0, 10) };
    private readonly UpdaterSplashView splashView = new();

    private readonly string? startupArgument;
    private ReleaseFeed? currentFeed;
    private LauncherMode launcherMode = LauncherMode.Manual;
    private string? currentStepKey;
    private string? lastSupportLogPath;
    private Mutex? modeMutex;
    private bool ownsModeMutex;
    private string? startupRequestId;
    private string? startupLockFailedUrl;
    private string? activeUpdateRequestId;
    private bool autoUpdateMode;
    private bool criticalUpdateStep;
    private bool detailsExpanded;

    public MainForm(string? startupArgument = null)
    {
        this.startupArgument = startupArgument;
        Text = "GarmentsOS PRO Launcher";
        Icon = TryLoadApplicationIcon();
        ClientSize = new Size(720, 420);
        MinimumSize = new Size(720, 420);
        MaximumSize = new Size(720, 420);
        StartPosition = FormStartPosition.CenterScreen;
        BackColor = AppBackground;
        Font = new Font("Segoe UI", 9f);
        FormBorderStyle = FormBorderStyle.None;
        MaximizeBox = false;
        DoubleBuffered = true;
        KeyPreview = true;

        Controls.Add(BuildLayout());
        ContextMenuStrip = BuildContextMenu();
        MouseDown += (_, e) => BeginDrag(e);
        KeyDown += (_, e) =>
        {
            if (e.KeyCode == Keys.Escape && !criticalUpdateStep)
            {
                Close();
            }
        };
        Resize += (_, _) => SetRoundedRegion();
        Shown += (_, _) => SetRoundedRegion();

        Load += async (_, _) =>
        {
            if (await HandlePendingLauncherUpdateOnStartupAsync())
            {
                return;
            }

            await RefreshStatusAsync();
            await HandleStartupArgumentAsync();
        };
    }

    protected override void OnFormClosing(FormClosingEventArgs e)
    {
        if (criticalUpdateStep)
        {
            e.Cancel = true;
            Log("Update is in progress. Please wait until the current step finishes.");
            return;
        }

        base.OnFormClosing(e);
    }

    protected override void OnFormClosed(FormClosedEventArgs e)
    {
        ReleaseModeMutex();
        base.OnFormClosed(e);
    }

    private ContextMenuStrip BuildContextMenu()
    {
        var menu = new ContextMenuStrip();
        menu.Items.Add("Open App", null, async (_, _) => await OpenAppAsync());
        menu.Items.Add("Check Update", null, async (_, _) => await CheckUpdateAsync());
        menu.Items.Add("Update Now", null, async (_, _) => await UpdateNowAsync(requireConfirmation: true, closeAfterSuccess: false));
        menu.Items.Add("Open Request JSON", null, async (_, _) => await OpenRequestJsonAsync());
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add("Backup", null, async (_, _) => await RunInstalledScriptAsync("scripts\\windows-docker-backup.ps1"));
        menu.Items.Add("Repair", null, async (_, _) => await RunPowerShellAsync("docker compose up -d", installDirBox.Text));
        menu.Items.Add("Fresh Reset / Install", null, async (_, _) => await ConfirmAndFreshResetInstallAsync());
        menu.Items.Add("Stop Services", null, async (_, _) => await StopServicesAsync());
        menu.Items.Add("Open Install Folder", null, (_, _) => OpenFolder(installDirBox.Text));
        menu.Items.Add("Save Log", null, (_, _) => SaveLog());
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add("Close", null, (_, _) => Close());
        return menu;
    }

    private Control BuildLayout()
    {
        var root = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = AppBackground,
            Padding = new Padding(0),
        };

        splashView.Dock = DockStyle.Fill;
        splashView.LogoImage = TryLoadLogoBitmap(24);
        ConfigureSplashForLauncher();
        splashView.InstalledVersionText = "Installed version: checking...";
        splashView.MouseDown += (_, e) => BeginDrag(e);
        root.Controls.Add(splashView);

        // Hidden fields keep the existing update/feed logic intact without cluttering the UI.
        installDirBox.Visible = false;
        feedUrlBox.Visible = false;
        pathsPanel.Visible = false;
        pathsPanel.Controls.Add(installDirBox);
        pathsPanel.Controls.Add(feedUrlBox);
        root.Controls.Add(pathsPanel);

        logBox.Visible = false;
        logBox.Multiline = true;
        logBox.ReadOnly = true;
        logBox.ScrollBars = ScrollBars.Vertical;
        logBox.Width = 640;
        logBox.Height = 130;
        logBox.Left = 44;
        logBox.Top = 222;
        logBox.Anchor = AnchorStyles.Left | AnchorStyles.Right | AnchorStyles.Bottom;
        root.Controls.Add(logBox);

        ConfigureActionButtons();
        failureButtonsPanel.Dock = DockStyle.None;
        failureButtonsPanel.Left = 52;
        failureButtonsPanel.Top = 372;
        failureButtonsPanel.BackColor = Color.Transparent;
        failureButtonsPanel.Visible = false;
        root.Controls.Add(failureButtonsPanel);
        failureButtonsPanel.BringToFront();

        return root;
    }

    private static Icon? TryLoadApplicationIcon()
    {
        try
        {
            return string.IsNullOrWhiteSpace(Environment.ProcessPath)
                ? null
                : Icon.ExtractAssociatedIcon(Environment.ProcessPath);
        }
        catch
        {
            return null;
        }
    }

    private Control BuildSideStrip()
    {
        var side = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = Color.FromArgb(245, 247, 251),
            Padding = new Padding(0, 28, 0, 28),
        };
        side.Paint += (_, e) =>
        {
            using var pen = new Pen(Color.FromArgb(229, 233, 240));
            e.Graphics.DrawLine(pen, side.Width - 1, 0, side.Width - 1, side.Height);
        };

        var logo = CreateLogoBox(46, 36, 14);
        logo.Left = (side.Width - logo.Width) / 2;
        logo.Top = 28;
        logo.Anchor = AnchorStyles.Top;
        side.Controls.Add(logo);

        var railText = new VerticalLabel
        {
            Text = "TOOLS",
            ForeColor = BrandBlue,
            Font = new Font("Segoe UI", 8.5f, FontStyle.Bold),
            Width = 34,
            Height = 120,
            Left = 39,
            Top = 132,
            Anchor = AnchorStyles.Top,
        };
        side.Controls.Add(railText);

        var check = new RoundedPanel
        {
            Width = 32,
            Height = 32,
            Left = 40,
            Top = 326,
            Anchor = AnchorStyles.Bottom,
            BorderRadius = 16,
            BorderColor = SoftBorder,
            BackColor = Color.White,
        };
        check.Controls.Add(new Label
        {
            Dock = DockStyle.Fill,
            Text = "✓",
            TextAlign = ContentAlignment.MiddleCenter,
            ForeColor = BrandBlue,
            Font = new Font("Segoe UI", 10f, FontStyle.Bold),
            BackColor = Color.Transparent,
        });
        side.Controls.Add(check);

        side.Resize += (_, _) =>
        {
            logo.Left = (side.Width - logo.Width) / 2;
            railText.Left = (side.Width - railText.Width) / 2;
            check.Left = (side.Width - check.Width) / 2;
            check.Top = Math.Max(280, side.Height - 60);
        };

        return side;
    }

    private Control BuildMainArea()
    {
        var main = new GridPanel
        {
            Dock = DockStyle.Fill,
            BackColor = Color.White,
        };

        var layout = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            RowCount = 3,
            ColumnCount = 1,
            BackColor = Color.Transparent,
        };
        layout.RowStyles.Add(new RowStyle(SizeType.Absolute, 68));
        layout.RowStyles.Add(new RowStyle(SizeType.Percent, 100));
        layout.RowStyles.Add(new RowStyle(SizeType.Absolute, 78));
        main.Controls.Add(layout);

        layout.Controls.Add(BuildTopBar(), 0, 0);
        layout.Controls.Add(BuildContent(), 0, 1);
        layout.Controls.Add(BuildBottomBar(), 0, 2);

        return main;
    }

    private Control BuildTopBar()
    {
        var top = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = Color.Transparent,
            Padding = new Padding(34, 0, 34, 0),
        };
        top.Paint += (_, e) =>
        {
            using var pen = new Pen(Color.FromArgb(237, 240, 244));
            e.Graphics.DrawLine(pen, 0, top.Height - 1, top.Width, top.Height - 1);
        };

        titleLabel.Text = "GarmentsOS PRO";
        titleLabel.Font = new Font("Segoe UI", 10.5f, FontStyle.Bold);
        titleLabel.ForeColor = BrandBlue;
        titleLabel.AutoSize = true;
        titleLabel.BackColor = Color.Transparent;
        titleLabel.Location = new Point(34, 25);
        top.Controls.Add(titleLabel);

        var status = new FlowLayoutPanel
        {
            AutoSize = true,
            FlowDirection = FlowDirection.LeftToRight,
            WrapContents = false,
            BackColor = Color.Transparent,
            Anchor = AnchorStyles.Top | AnchorStyles.Right,
            Location = new Point(430, 22),
        };
        status.Controls.Add(new StatusDot { DotColor = Color.FromArgb(23, 168, 91), Margin = new Padding(0, 6, 8, 0) });
        status.Controls.Add(new Label
        {
            Text = "Secure Launcher",
            AutoSize = true,
            Font = new Font("Segoe UI", 8.4f, FontStyle.Bold),
            ForeColor = Color.FromArgb(61, 75, 96),
            Margin = new Padding(0, 3, 0, 0),
            BackColor = Color.Transparent,
        });
        top.Controls.Add(status);
        top.Resize += (_, _) => status.Left = top.Width - status.Width - 34;

        return top;
    }

    private Control BuildContent()
    {
        var content = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 2,
            RowCount = 1,
            Padding = new Padding(34, 30, 34, 18),
            BackColor = Color.Transparent,
        };
        content.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
        content.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 220));

        var copy = new Panel { Dock = DockStyle.Fill, BackColor = Color.Transparent };
        copy.Controls.Add(new PillLabel
        {
            Text = "RELEASE FEED",
            Location = new Point(0, 0),
            Width = 112,
            Height = 28,
        });

        var headline = new RichTextLabel
        {
            NormalText = "Starting",
            AccentText = "GarmentsOS PRO",
            FontSize = 33f,
            Location = new Point(0, 45),
            Width = 335,
            Height = 82,
        };
        copy.Controls.Add(headline);

        var sub = new Label
        {
            Text = "GarmentsOS PRO is preparing local services and opening the app.",
            Location = new Point(0, 130),
            Width = 335,
            Height = 46,
            Font = new Font("Segoe UI", 9.4f),
            ForeColor = TextMuted,
            BackColor = Color.Transparent,
        };
        copy.Controls.Add(sub);

        var chips = new FlowLayoutPanel
        {
            Location = new Point(0, 190),
            AutoSize = true,
            WrapContents = false,
            BackColor = Color.Transparent,
        };
        chips.Controls.Add(new PillLabel { Text = "Docker", Width = 78, Height = 28 });
        chips.Controls.Add(new PillLabel { Text = "Local App", Width = 82, Height = 28 });
        chips.Controls.Add(new PillLabel { Text = "Browser", Width = 72, Height = 28 });
        copy.Controls.Add(chips);

        ConfigureActionButtons();
        copy.Controls.Add(failureButtonsPanel);

        content.Controls.Add(copy, 0, 0);
        content.Controls.Add(BuildWindowStackVisual(), 1, 0);

        return content;
    }

    private Control BuildWindowStackVisual()
    {
        var visual = new WindowStackVisual
        {
            Dock = DockStyle.Fill,
            Logo = TryLoadLogoBitmap(26),
        };
        return visual;
    }

    private Control BuildBottomBar()
    {
        var bottom = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = Color.Transparent,
            Padding = new Padding(34, 0, 34, 23),
        };

        progressStatusLabel.Text = "Preparing update";
        progressStatusLabel.AutoSize = true;
        progressStatusLabel.Font = new Font("Segoe UI", 8.5f, FontStyle.Bold);
        progressStatusLabel.ForeColor = Color.FromArgb(61, 75, 96);
        progressStatusLabel.Location = new Point(34, 0);
        progressStatusLabel.BackColor = Color.Transparent;
        bottom.Controls.Add(progressStatusLabel);

        progressPercentLabel.Text = "0%";
        progressPercentLabel.Font = new Font("Segoe UI", 8.5f, FontStyle.Bold);
        progressPercentLabel.ForeColor = BrandBlue;
        progressPercentLabel.BackColor = Color.Transparent;
        progressPercentLabel.Anchor = AnchorStyles.Top | AnchorStyles.Right;
        progressPercentLabel.Location = new Point(500, 0);
        bottom.Controls.Add(progressPercentLabel);
        bottom.Resize += (_, _) => progressPercentLabel.Left = bottom.Width - progressPercentLabel.Width - 34;

        progressBar.Style = ProgressBarStyle.Continuous;
        progressBar.MarqueeAnimationSpeed = 0;
        progressBar.Value = 0;
        progressBar.Height = 8;
        progressBar.Width = 510;
        progressBar.Location = new Point(34, 24);
        progressBar.Anchor = AnchorStyles.Left | AnchorStyles.Right | AnchorStyles.Top;
        bottom.Controls.Add(progressBar);
        bottom.Resize += (_, _) => progressBar.Width = Math.Max(100, bottom.Width - 68);

        var product = new Label
        {
            Text = "A Product of SparkPair",
            AutoSize = true,
            Font = new Font("Segoe UI", 8.5f),
            ForeColor = TextHint,
            BackColor = Color.Transparent,
            Location = new Point(34, 46),
        };
        bottom.Controls.Add(product);

        installedFooterLabel.Text = "Installed version: checking...";
        installedFooterLabel.Font = new Font("Segoe UI", 8.5f);
        installedFooterLabel.ForeColor = TextHint;
        installedFooterLabel.BackColor = Color.Transparent;
        installedFooterLabel.Anchor = AnchorStyles.Bottom | AnchorStyles.Right;
        installedFooterLabel.Location = new Point(470, 46);
        bottom.Controls.Add(installedFooterLabel);
        bottom.Resize += (_, _) => installedFooterLabel.Left = bottom.Width - installedFooterLabel.Width - 34;

        return bottom;
    }

    private Control BuildProgressPanel()
    {
        var panel = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = Color.Transparent,
        };

        var heading = new Label
        {
            Text = "Updating GarmentsOS PRO",
            Font = new Font("Segoe UI", 16f, FontStyle.Regular),
            ForeColor = TextPrimary,
            AutoSize = true,
            Location = new Point(0, 0),
            BackColor = Color.Transparent,
        };
        panel.Controls.Add(heading);

        progressStatusLabel.Location = new Point(0, 38);
        progressStatusLabel.Font = new Font("Segoe UI", 9f, FontStyle.Bold);
        progressStatusLabel.ForeColor = TextMuted;
        panel.Controls.Add(progressStatusLabel);

        progressBar.Location = new Point(0, 66);
        progressBar.Width = 400;
        progressBar.Height = 8;
        panel.Controls.Add(progressBar);

        detailsButton.Click += (_, _) => ToggleDetails();
        StyleButton(detailsButton, "Details");
        detailsButton.Location = new Point(0, 90);
        panel.Controls.Add(detailsButton);

        return panel;
    }

    private void ConfigureActionButtons()
    {
        buttonsPanel.Controls.Clear();
        buttonsPanel.Visible = false;
        buttonsPanel.Location = new Point(0, 231);
        buttonsPanel.AutoSize = true;
        buttonsPanel.WrapContents = false;
        buttonsPanel.BackColor = Color.Transparent;

        StyleButton(updateButton, "Update", primary: true);
        updateButton.Click += async (_, _) => await UpdateNowAsync(requireConfirmation: true, closeAfterSuccess: false);
        buttonsPanel.Controls.Add(updateButton);

        AddButton(buttonsPanel, "Details", (_, _) => ToggleDetails());
        AddButton(buttonsPanel, "Open App", async (_, _) => await OpenAppAsync());
        AddButton(buttonsPanel, "Check", async (_, _) => await CheckUpdateAsync());

        failureButtonsPanel.Dock = DockStyle.None;
        failureButtonsPanel.Location = new Point(52, 372);
        failureButtonsPanel.AutoSize = true;
        failureButtonsPanel.WrapContents = false;
        failureButtonsPanel.BackColor = Color.Transparent;
        ConfigureFailureButtons(FailureActionMode.General);
    }

    private void ConfigureFailureButtons(FailureActionMode mode)
    {
        failureButtonsPanel.SuspendLayout();
        failureButtonsPanel.Controls.Clear();

        if (mode == FailureActionMode.Docker)
        {
            AddButton(failureButtonsPanel, "Start Docker / Retry", async (_, _) => await OpenAppAsync(), primary: true);
            AddButton(failureButtonsPanel, "Details", (_, _) => ToggleDetails());
            AddButton(failureButtonsPanel, "Close", (_, _) => Close());
        }
        else
        {
            AddButton(failureButtonsPanel, "Details", (_, _) => ToggleDetails());
            AddButton(failureButtonsPanel, "Save Log", (_, _) => SaveLog());
            AddButton(failureButtonsPanel, "Open Support Folder", (_, _) => OpenSupportFolder());
            AddButton(failureButtonsPanel, "Close", (_, _) => Close());
        }

        failureButtonsPanel.Visible = false;
        failureButtonsPanel.ResumeLayout();
    }

    private static Button AddButton(Control parent, string text, EventHandler handler, bool primary = false)
    {
        var button = new Button();
        StyleButton(button, text, primary);
        button.Click += handler;
        parent.Controls.Add(button);
        return button;
    }

    private static void StyleButton(Button button, string text, bool primary = false)
    {
        button.Text = text;
        button.AutoSize = false;
        button.Font = new Font("Segoe UI", 8.2f, FontStyle.Bold);
        var measuredWidth = TextRenderer.MeasureText(text, button.Font).Width + 28;
        button.Width = primary ? Math.Max(76, measuredWidth) : Math.Max(72, measuredWidth);
        button.Height = 32;
        button.Margin = new Padding(0, 0, 8, 0);
        button.Padding = new Padding(8, 0, 8, 0);
        button.FlatStyle = FlatStyle.Flat;
        button.UseVisualStyleBackColor = false;
        button.Cursor = Cursors.Hand;
        button.BackColor = primary ? BrandBlue : Color.White;
        button.ForeColor = primary ? Color.White : Color.FromArgb(64, 82, 82);
        button.FlatAppearance.BorderColor = primary ? BrandBlue : SoftBorder;
        button.FlatAppearance.BorderSize = 1;
        button.FlatAppearance.MouseOverBackColor = primary ? Color.FromArgb(50, 72, 210) : SurfaceSoft;
        button.FlatAppearance.MouseDownBackColor = primary ? Color.FromArgb(29, 78, 216) : Color.FromArgb(241, 245, 249);
        button.TextAlign = ContentAlignment.MiddleCenter;
        button.TabStop = false;
        ApplyButtonRegion(button);
        button.Resize += (_, _) => ApplyButtonRegion(button);
    }

    private static void ApplyButtonRegion(Button button)
    {
        if (button.Width <= 0 || button.Height <= 0)
        {
            return;
        }

        using var path = RoundedRect(new Rectangle(0, 0, button.Width, button.Height), 10);
        button.Region?.Dispose();
        button.Region = new Region(path);
    }

    private Control CreateLogoBox(int size, int imageSize, int radius)
    {
        var box = new RoundedPanel
        {
            Width = size,
            Height = size,
            BorderRadius = radius,
            BorderColor = SoftBorder,
            BackColor = Color.White,
        };

        var logo = TryLoadLogoBitmap(imageSize);
        if (logo is not null)
        {
            box.Controls.Add(new PictureBox
            {
                Image = logo,
                Dock = DockStyle.Fill,
                SizeMode = PictureBoxSizeMode.CenterImage,
                BackColor = Color.Transparent,
            });
            return box;
        }

        box.Controls.Add(new Label
        {
            Text = "G",
            Dock = DockStyle.Fill,
            TextAlign = ContentAlignment.MiddleCenter,
            Font = new Font("Segoe UI", 13f, FontStyle.Bold),
            ForeColor = BrandBlue,
            BackColor = Color.Transparent,
        });
        return box;
    }

    private static Bitmap? TryLoadLogoBitmap(int size)
    {
        var candidates = new[]
        {
            Path.Combine(AppContext.BaseDirectory, "garmentsos_pro_logo.png"),
            Path.Combine(AppContext.BaseDirectory, "favicon.ico"),
            Path.Combine(AppContext.BaseDirectory, "favicon(1).ico"),
        };

        foreach (var path in candidates)
        {
            if (!File.Exists(path))
            {
                continue;
            }

            try
            {
                if (Path.GetExtension(path).Equals(".ico", StringComparison.OrdinalIgnoreCase))
                {
                    return new Icon(path, size, size).ToBitmap();
                }

                using var source = Image.FromFile(path);
                return new Bitmap(source, new Size(size, size));
            }
            catch
            {
                // Ignore invalid branding assets and use fallback text.
            }
        }

        return null;
    }

    private void ConfigureSplashForLauncher()
    {
        SetMode(LauncherMode.Manual);
    }

    private void ConfigureSplashForInstall()
    {
        SetMode(LauncherMode.Install);
    }

    private void ConfigureSplashForOpen()
    {
        SetMode(LauncherMode.Open);
    }

    private void ConfigureSplashForUpdate()
    {
        SetMode(LauncherMode.Update);
    }

    private void SetMode(LauncherMode mode)
    {
        launcherMode = mode;
        currentStepKey = null;
        SetWorkingStateUI();

        splashView.BadgeText = "Secure Launcher";
        splashView.ProgressPercent = 0;
        splashView.IsIndeterminate = false;
        splashView.ErrorText = null;
        splashView.Steps = StepsForMode(mode);

        switch (mode)
        {
            case LauncherMode.Install:
                splashView.TitleLine1 = "Installing";
                splashView.TitleLine2 = "GarmentsOS PRO";
                splashView.Subtitle = "Setting up the app on this computer.";
                splashView.HelperText = "Please do not close this window.";
                splashView.ProgressText = "Preparing installation...";
                SetCurrentStep("check_docker", "Checking Docker", 10);
                break;
            case LauncherMode.Open:
                splashView.TitleLine1 = "Starting";
                splashView.TitleLine2 = "GarmentsOS PRO";
                splashView.Subtitle = "Starting local services and opening the app.";
                splashView.HelperText = "Starting the app. This may take a moment.";
                splashView.ProgressText = "Starting GarmentsOS PRO...";
                SetCurrentStep("check_docker", "Checking Docker", 20);
                break;
            case LauncherMode.Update:
                splashView.TitleLine1 = "Updating";
                splashView.TitleLine2 = "GarmentsOS PRO";
                splashView.Subtitle = "Applying a safe local update. Please wait.";
                splashView.HelperText = "Please do not close this window.";
                splashView.ProgressText = "Preparing update...";
                SetCurrentStep("download_update", "Preparing update", 10);
                break;
            default:
                splashView.TitleLine1 = "GarmentsOS";
                splashView.TitleLine2 = "PRO";
                splashView.Subtitle = "Ready to start, install, or update.";
                splashView.HelperText = "";
                splashView.ProgressText = "Ready";
                break;
        }

        splashView.Invalidate();
    }

    private void SetWorkingStateUI()
    {
        buttonsPanel.Visible = false;
        failureButtonsPanel.Visible = false;
        logBox.Visible = false;
        detailsExpanded = false;
        detailsButton.Text = "Details";
    }

    private void SetStepDetail(string detail)
    {
        splashView.HelperText = detail;
        splashView.Invalidate();
    }

    private static List<LauncherStep> StepsForMode(LauncherMode mode)
    {
        return mode switch
        {
            LauncherMode.Install => new List<LauncherStep>
            {
                NewStep("check_docker", "Check Docker"),
                NewStep("download_package", "Download package"),
                NewStep("verify_package", "Verify package"),
                NewStep("install_files", "Install files"),
                NewStep("start_services", "Start services"),
                NewStep("open_app", "Open app"),
            },
            LauncherMode.Open => new List<LauncherStep>
            {
                NewStep("check_docker", "Check Docker"),
                NewStep("start_services", "Start services"),
                NewStep("wait_app", "Wait for app"),
                NewStep("open_app", "Open app"),
            },
            LauncherMode.Update => new List<LauncherStep>
            {
                NewStep("download_update", "Download update"),
                NewStep("verify_package", "Verify package"),
                NewStep("create_backup", "Create backup"),
                NewStep("apply_update", "Apply update"),
                NewStep("restart_services", "Restart services"),
                NewStep("open_app", "Open app"),
            },
            _ => new List<LauncherStep>(),
        };
    }

    private static LauncherStep NewStep(string key, string label)
    {
        return new LauncherStep { Key = key, Label = label };
    }

    private void SetCurrentStep(string key, string? message = null, int? percent = null)
    {
        currentStepKey = key;
        var steps = splashView.Steps;
        var index = steps.FindIndex(step => string.Equals(step.Key, key, StringComparison.OrdinalIgnoreCase));

        for (var i = 0; i < steps.Count; i++)
        {
            steps[i].State = i < index
                ? LauncherStepState.Done
                : i == index
                    ? LauncherStepState.Current
                    : LauncherStepState.Pending;
        }

        if (!string.IsNullOrWhiteSpace(message))
        {
            progressStatusLabel.Text = message;
            splashView.ProgressText = message;
        }

        ApplyStepDetail(key, message);

        var nextPercent = Math.Clamp(percent ?? ProgressForStep(launcherMode, key), progressBar.Minimum, progressBar.Maximum);
        progressBar.Value = nextPercent;
        progressPercentLabel.Text = $"{nextPercent}%";
        splashView.ProgressPercent = nextPercent;
        splashView.ErrorText = null;
        splashView.Invalidate();
    }

    private void ApplyStepDetail(string key, string? message)
    {
        if (!string.IsNullOrWhiteSpace(message)
            && (message.StartsWith("Downloading package:", StringComparison.OrdinalIgnoreCase)
                || message.Contains("taking longer", StringComparison.OrdinalIgnoreCase)
                || message.StartsWith("Package size:", StringComparison.OrdinalIgnoreCase)))
        {
            return;
        }

        splashView.HelperText = key switch
        {
            "download_package" => "Downloading installation package. This can take a few minutes.",
            "download_update" => "Downloading update package. This can take a few minutes.",
            "check_docker" when launcherMode is LauncherMode.Install or LauncherMode.Open => "Starting Docker Desktop. This may take 1-2 minutes.",
            "wait_app" => "Waiting for local app to respond.",
            "start_services" => "Starting local app services.",
            "open_app" => launcherMode == LauncherMode.Open
                ? "Opening GarmentsOS PRO in your browser."
                : "Please do not close this window.",
            _ => launcherMode == LauncherMode.Open
                ? "Starting the app. This may take a moment."
                : "Please do not close this window.",
        };
    }

    private void MarkAllStepsDone(string? message = null)
    {
        foreach (var step in splashView.Steps)
        {
            step.State = LauncherStepState.Done;
        }

        currentStepKey = null;
        if (!string.IsNullOrWhiteSpace(message))
        {
            progressStatusLabel.Text = message;
            splashView.ProgressText = message;
        }

        progressBar.Value = 100;
        progressPercentLabel.Text = "100%";
        splashView.ProgressPercent = 100;
        splashView.IsIndeterminate = false;
        splashView.ErrorText = null;
        splashView.Invalidate();
    }

    private void FailCurrentStep(string friendlyMessage)
    {
        var steps = splashView.Steps;
        var key = currentStepKey ?? steps.FirstOrDefault(step => step.State == LauncherStepState.Current)?.Key;
        var index = key is null ? -1 : steps.FindIndex(step => string.Equals(step.Key, key, StringComparison.OrdinalIgnoreCase));

        if (index >= 0)
        {
            for (var i = 0; i < steps.Count; i++)
            {
                steps[i].State = i < index
                    ? LauncherStepState.Done
                    : i == index
                        ? LauncherStepState.Failed
                        : LauncherStepState.Pending;
            }
        }

        splashView.TitleLine1 = "Something";
        splashView.TitleLine2 = "went wrong";
        splashView.Subtitle = friendlyMessage;
        splashView.HelperText = "Open details if support asks for the technical log.";
        splashView.ErrorText = ShortUiMessage(friendlyMessage);
        splashView.IsIndeterminate = false;
        splashView.Invalidate();
    }

    private static int ProgressForStep(LauncherMode mode, string key)
    {
        return mode switch
        {
            LauncherMode.Install => key switch
            {
                "check_docker" => 10,
                "download_package" => 30,
                "verify_package" => 45,
                "install_files" => 60,
                "start_services" => 80,
                "open_app" => 95,
                _ => 10,
            },
            LauncherMode.Open => key switch
            {
                "check_docker" => 20,
                "start_services" => 50,
                "wait_app" => 75,
                "open_app" => 95,
                _ => 20,
            },
            LauncherMode.Update => key switch
            {
                "download_update" => 30,
                "verify_package" => 50,
                "create_backup" => 65,
                "apply_update" => 78,
                "restart_services" => 90,
                "open_app" => 95,
                _ => 20,
            },
            _ => 0,
        };
    }

    private void EnterAutoUpdateMode()
    {
        ConfigureSplashForUpdate();
        autoUpdateMode = true;
        criticalUpdateStep = false;
        detailsExpanded = false;
        Text = "GarmentsOS PRO Updating";
        titleLabel.Text = "GarmentsOS PRO";
        failureButtonsPanel.Visible = false;
        logBox.Visible = false;
        ControlBox = false;
        SetStep("Preparing update...", percent: 0);
    }

    private void EnterManualBridgeMode()
    {
        EnterAutoUpdateMode();
        SetStep("Preparing manual update...", percent: 10);
        splashView.HelperText = "Fetching the latest approved release directly from SparkPair.";
        splashView.Invalidate();
    }

    private void ToggleDetails()
    {
        detailsExpanded = !detailsExpanded;

        logBox.Dock = DockStyle.None;
        logBox.Left = 52;
        logBox.Top = 214;
        logBox.Width = Math.Max(100, ClientSize.Width - 104);
        logBox.Height = 130;
        logBox.Visible = detailsExpanded;
        logBox.BringToFront();

        detailsButton.Text = detailsExpanded ? "Hide Details" : "Details";
    }

    private void SetStep(string message, int? percent = null, bool marquee = false)
    {
        if (InvokeRequired)
        {
            BeginInvoke(() => SetStep(message, percent, marquee));
            return;
        }

        progressStatusLabel.Text = message;

        var isIndeterminate = marquee && !percent.HasValue;
        var stepKey = StepKeyForMessage(launcherMode, message);
        var nextPercent = percent
            ?? (stepKey is not null
                ? ProgressForStep(launcherMode, stepKey)
                : isIndeterminate
                    ? Math.Max(progressBar.Value, StepPercent(message, progressBar.Value))
                    : progressBar.Value);

        if (stepKey is not null)
        {
            SetCurrentStep(stepKey, message, nextPercent);
            if (nextPercent >= 100
                || message.Contains("Ready", StringComparison.OrdinalIgnoreCase)
                || message.Contains("complete", StringComparison.OrdinalIgnoreCase))
            {
                MarkAllStepsDone(message);
            }
        }
        else if (nextPercent >= 100
            || message.Contains("Ready", StringComparison.OrdinalIgnoreCase)
            || message.Contains("complete", StringComparison.OrdinalIgnoreCase))
        {
            MarkAllStepsDone(message);
        }

        progressBar.Style = ProgressBarStyle.Continuous;
        progressBar.MarqueeAnimationSpeed = 0;
        progressBar.Value = Math.Clamp(nextPercent, progressBar.Minimum, progressBar.Maximum);

        progressPercentLabel.Text = isIndeterminate ? "Working..." : $"{progressBar.Value}%";

        splashView.ProgressText = message;
        splashView.ProgressPercent = progressBar.Value;
        splashView.IsIndeterminate = isIndeterminate;
        if (!message.Contains("failed", StringComparison.OrdinalIgnoreCase))
        {
            splashView.ErrorText = null;
        }
        splashView.Invalidate();

        progressPercentLabel.Left = progressPercentLabel.Parent is null
            ? progressPercentLabel.Left
            : progressPercentLabel.Parent.Width - progressPercentLabel.Width - 34;
    }

    private static int StepPercent(string message, int current)
    {
        if (message.Contains("Downloading", StringComparison.OrdinalIgnoreCase)) return Math.Max(current, 38);
        if (message.Contains("Verifying", StringComparison.OrdinalIgnoreCase)) return Math.Max(current, 54);
        if (message.Contains("backup", StringComparison.OrdinalIgnoreCase)) return Math.Max(current, 64);
        if (message.Contains("Applying", StringComparison.OrdinalIgnoreCase) || message.Contains("Loaded image", StringComparison.OrdinalIgnoreCase)) return Math.Max(current, 78);
        if (message.Contains("Restarting", StringComparison.OrdinalIgnoreCase) || message.Contains("started", StringComparison.OrdinalIgnoreCase)) return Math.Max(current, 90);
        if (message.Contains("Opening", StringComparison.OrdinalIgnoreCase)) return Math.Max(current, 95);
        if (message.Contains("Preparing", StringComparison.OrdinalIgnoreCase)) return Math.Max(current, 24);
        return current <= 0 ? 20 : current;
    }

    private static string? StepKeyForMessage(LauncherMode mode, string message)
    {
        var text = message.Trim();

        return mode switch
        {
            LauncherMode.Install => InstallStepKey(text),
            LauncherMode.Open => OpenStepKey(text),
            LauncherMode.Update => UpdateStepKey(text),
            _ => null,
        };
    }

    private static string? InstallStepKey(string message)
    {
        if (IsDockerMessage(message)) return "check_docker";
        if (message.Contains("Downloading", StringComparison.OrdinalIgnoreCase)) return "download_package";
        if (message.Contains("Verifying", StringComparison.OrdinalIgnoreCase)) return "verify_package";
        if (message.Contains("Extracting", StringComparison.OrdinalIgnoreCase)
            || message.Contains("Creating environment", StringComparison.OrdinalIgnoreCase)
            || message.Contains("Loading Docker image", StringComparison.OrdinalIgnoreCase)
            || message.Contains("Creating shortcuts", StringComparison.OrdinalIgnoreCase)) return "install_files";
        if (message.Contains("Starting app services", StringComparison.OrdinalIgnoreCase)) return "start_services";
        if (message.Contains("Opening", StringComparison.OrdinalIgnoreCase) || message.Contains("Ready", StringComparison.OrdinalIgnoreCase)) return "open_app";
        return null;
    }

    private static string? OpenStepKey(string message)
    {
        if (IsDockerMessage(message)) return "check_docker";
        if (message.Contains("Starting app services", StringComparison.OrdinalIgnoreCase)) return "start_services";
        if (message.Contains("Waiting for app", StringComparison.OrdinalIgnoreCase)) return "wait_app";
        if (message.Contains("Opening", StringComparison.OrdinalIgnoreCase) || message.Contains("Ready", StringComparison.OrdinalIgnoreCase)) return "open_app";
        return null;
    }

    private static string? UpdateStepKey(string message)
    {
        if (message.Contains("Downloading", StringComparison.OrdinalIgnoreCase)
            || message.Equals("Preparing update...", StringComparison.OrdinalIgnoreCase)
            || message.Equals("Preparing update", StringComparison.OrdinalIgnoreCase)) return "download_update";
        if (message.Contains("Verifying", StringComparison.OrdinalIgnoreCase)
            || message.Contains("Preparing update files", StringComparison.OrdinalIgnoreCase)) return "verify_package";
        if (message.Contains("backup", StringComparison.OrdinalIgnoreCase)) return "create_backup";
        if (message.Contains("Applying", StringComparison.OrdinalIgnoreCase)
            || message.Contains("Loaded image", StringComparison.OrdinalIgnoreCase)) return "apply_update";
        if (message.Contains("Restarting", StringComparison.OrdinalIgnoreCase)
            || message.Contains("started", StringComparison.OrdinalIgnoreCase)) return "restart_services";
        if (message.Contains("Opening", StringComparison.OrdinalIgnoreCase)
            || message.Contains("complete", StringComparison.OrdinalIgnoreCase)) return "open_app";
        return null;
    }

    private static bool IsDockerMessage(string message)
    {
        return message.Contains("Checking Docker", StringComparison.OrdinalIgnoreCase)
            || message.Contains("Starting Docker", StringComparison.OrdinalIgnoreCase)
            || message.Contains("Waiting for Docker", StringComparison.OrdinalIgnoreCase);
    }

    private void ShowFailureMode(string message = "Update failed", string title = "Update failed", FailureActionMode? actionMode = null)
    {
        autoUpdateMode = false;
        criticalUpdateStep = false;
        ControlBox = true;
        updateButton.Enabled = currentFeed is not null;
        SaveLauncherErrorLog(title, message);

        progressStatusLabel.Text = title;
        progressBar.Value = 0;
        progressPercentLabel.Text = "0%";
        FailCurrentStep(message);

        ConfigureFailureButtons(actionMode ?? FailureActionModeFor(message, title));
        failureButtonsPanel.Dock = DockStyle.None;
        failureButtonsPanel.Left = 52;
        failureButtonsPanel.Top = 372;
        failureButtonsPanel.Visible = true;
        failureButtonsPanel.BringToFront();

        logBox.Visible = false;
        detailsExpanded = false;
        detailsButton.Text = "Details";
    }

    private static FailureActionMode FailureActionModeFor(string message, string title)
    {
        return IsDockerFailureMessage(message) || IsDockerFailureMessage(title)
            ? FailureActionMode.Docker
            : FailureActionMode.General;
    }

    private static bool IsDockerFailureMessage(string value)
    {
        return value.Contains("Docker", StringComparison.OrdinalIgnoreCase)
            || value.Contains("docker compose", StringComparison.OrdinalIgnoreCase);
    }

    private static string ShortUiMessage(string message)
    {
        var clean = string.IsNullOrWhiteSpace(message) ? "Unknown error. Open details for more information." : message.Trim();
        clean = clean.Replace("\r", " ").Replace("\n", " ");
        return clean.Length <= 96 ? clean : clean[..93] + "...";
    }

    private async Task RefreshStatusAsync()
    {
        var installDir = installDirBox.Text.Trim();
        var manifest = ReadInstalledManifest(installDir);
        var installedVersionText = string.IsNullOrWhiteSpace(manifest?.Version)
            ? "Fresh installation"
            : $"Installed version: {manifest.Version}";
        installedVersionLabel.Text = installedVersionText;
        installedFooterLabel.Text = installedVersionText;
        splashView.InstalledVersionText = installedVersionText;
        splashView.Invalidate();
        feedUrlBox.Text = ReadEnvValue(Path.Combine(installDir, ".env"), "UPDATE_FEED_URL")
            ?? ReadEnvValue(Path.Combine(installDir, ".env"), "UPDATER_MANIFEST_URL")
            ?? "";

        dockerStatusLabel.Text = "Docker status: checking...";
        appStatusLabel.Text = "App status: checking...";
        dockerStatusLabel.Text = $"Docker status: {await GetDockerStatusAsync()}";
        appStatusLabel.Text = $"App status: {await GetAppStatusAsync()}";
    }

    private async Task CheckUpdateAsync()
    {
        try
        {
            await RefreshStatusAsync();
            var feedUrl = feedUrlBox.Text.Trim();
            if (string.IsNullOrWhiteSpace(feedUrl))
            {
                Log("Update feed URL is not configured.");
                return;
            }

            currentFeed = await FetchFeedAsync(feedUrl);
            latestVersionLabel.Text = $"Latest version: {currentFeed.Version}";
            mandatoryLabel.Text = $"Mandatory: {currentFeed.Mandatory}";
            notesBox.Text = currentFeed.Notes;

            var installed = ReadInstalledManifest(installDirBox.Text.Trim())?.Version ?? "0.0.0";
            updateButton.Enabled = VersionCompare(currentFeed.Version, installed) > 0;
            Log(updateButton.Enabled ? $"Update available: {installed} -> {currentFeed.Version}" : "Installed app is up to date.");
        }
        catch (Exception ex)
        {
            currentFeed = null;
            updateButton.Enabled = false;
            Log("Update check failed: " + ex.Message);
        }
    }

    private async Task OpenRequestJsonAsync()
    {
        using var dialog = new OpenFileDialog
        {
            Title = "Open GarmentsOS update request",
            Filter = "GarmentsOS update request (*.json)|*.json|All files (*.*)|*.*",
        };

        if (dialog.ShowDialog(this) != DialogResult.OK)
        {
            return;
        }

        await LoadRequestJsonFromFileAsync(dialog.FileName);
    }

    private bool TryAcquireModeMutex(LauncherMode mode)
    {
        if (ownsModeMutex)
        {
            return true;
        }

        var mutexName = mode == LauncherMode.Update
            ? UpdateMutexName
            : mode == LauncherMode.Open
                ? OpenMutexName
                : null;

        if (mutexName is null)
        {
            return true;
        }

        try
        {
            modeMutex = new Mutex(false, mutexName);
            ownsModeMutex = modeMutex.WaitOne(0, false);
            if (ownsModeMutex)
            {
                Log("Launcher mutex acquired: " + mutexName);
                return true;
            }

            Log("Launcher mutex is already held: " + mutexName);
            TryBringExistingLauncherToFront();
            modeMutex.Dispose();
            modeMutex = null;
            return false;
        }
        catch (Exception ex)
        {
            Log("Could not acquire launcher mutex: " + ex.Message);
            return true;
        }
    }

    private void ReleaseModeMutex()
    {
        if (modeMutex is null)
        {
            return;
        }

        try
        {
            if (ownsModeMutex)
            {
                modeMutex.ReleaseMutex();
            }
        }
        catch
        {
        }
        finally
        {
            ownsModeMutex = false;
            modeMutex.Dispose();
            modeMutex = null;
        }
    }

    private static void TryBringExistingLauncherToFront()
    {
        try
        {
            var currentId = Environment.ProcessId;
            var processes = Process.GetProcesses()
                .Where(process =>
                    process.Id != currentId &&
                    (process.ProcessName.Equals("GarmentsOS-PRO", StringComparison.OrdinalIgnoreCase)
                        || process.ProcessName.Equals("GarmentsOS-PRO-Setup", StringComparison.OrdinalIgnoreCase)
                        || process.ProcessName.Equals("GarmentsOS PRO Launcher", StringComparison.OrdinalIgnoreCase)))
                .ToList();

            foreach (var process in processes)
            {
                if (process.MainWindowHandle == IntPtr.Zero)
                {
                    continue;
                }

                ShowWindow(process.MainWindowHandle, 9);
                SetForegroundWindow(process.MainWindowHandle);
                return;
            }
        }
        catch
        {
        }
    }

    private void CloseSoon(int milliseconds = 700)
    {
        var timer = new System.Windows.Forms.Timer { Interval = milliseconds };
        timer.Tick += (_, _) =>
        {
            timer.Stop();
            Close();
        };
        timer.Start();
    }

    private bool TryBeginActiveUpdate(string? requestId, string targetVersion)
    {
        var installDir = installDirBox.Text.Trim();
        var updatesDir = Path.Combine(installDir, "updates");
        var markerPath = ActiveUpdateMarkerPath(installDir);
        requestId = string.IsNullOrWhiteSpace(requestId) ? Guid.NewGuid().ToString("D") : requestId.Trim();

        try
        {
            Directory.CreateDirectory(updatesDir);

            if (File.Exists(markerPath))
            {
                var active = ReadActiveUpdateMarker(markerPath);
                if (active is not null && !ActiveUpdateExpired(active.Value.StartedAt))
                {
                    var existingId = active.Value.RequestId;
                    var message = string.Equals(existingId, requestId, StringComparison.OrdinalIgnoreCase)
                        ? "This update request is already running in another launcher window."
                        : "Another GarmentsOS PRO update is already running. Please wait for it to finish.";

                    Log(message);
                    ShowFailureMode(message, "Update already running");
                    return false;
                }

                var stalePath = Path.Combine(
                    Path.GetDirectoryName(markerPath) ?? updatesDir,
                    "active-update.stale-" + DateTime.Now.ToString("yyyyMMdd_HHmmss") + ".json");
                File.Move(markerPath, stalePath, overwrite: true);
                Log("Active update marker was stale and moved to: " + stalePath);
            }

            var marker = new
            {
                app = "garmentsos-pro",
                request_id = requestId,
                started_at = DateTimeOffset.UtcNow.ToString("O"),
                target_version = targetVersion,
            };

            File.WriteAllText(markerPath, JsonSerializer.Serialize(marker, new JsonSerializerOptions
            {
                WriteIndented = true,
            }));

            activeUpdateRequestId = requestId;
            Log("Active update marker created: " + markerPath);
            return true;
        }
        catch (Exception ex)
        {
            Log("Could not create active update marker: " + ex.Message);
            return true;
        }
    }

    private void ClearActiveUpdateMarker()
    {
        var markerPath = ActiveUpdateMarkerPath(installDirBox.Text.Trim());
        if (activeUpdateRequestId is null || !File.Exists(markerPath))
        {
            activeUpdateRequestId = null;
            return;
        }

        try
        {
            var active = ReadActiveUpdateMarker(markerPath);
            if (active is null || string.Equals(active.Value.RequestId, activeUpdateRequestId, StringComparison.OrdinalIgnoreCase))
            {
                File.Delete(markerPath);
                Log("Active update marker cleared.");
            }
        }
        catch (Exception ex)
        {
            Log("Could not clear active update marker: " + ex.Message);
        }
        finally
        {
            activeUpdateRequestId = null;
        }
    }

    private static string ActiveUpdateMarkerPath(string installDir)
    {
        return Path.Combine(installDir, "updates", "active-update.json");
    }

    private static (string RequestId, DateTimeOffset StartedAt)? ReadActiveUpdateMarker(string markerPath)
    {
        try
        {
            using var document = JsonDocument.Parse(File.ReadAllText(markerPath));
            var root = document.RootElement;
            var requestId = root.TryGetProperty("request_id", out var id) ? id.GetString() ?? "" : "";
            var startedAtText = root.TryGetProperty("started_at", out var started) ? started.GetString() ?? "" : "";

            if (!DateTimeOffset.TryParse(startedAtText, out var startedAt))
            {
                return null;
            }

            return (requestId, startedAt);
        }
        catch
        {
            return null;
        }
    }

    private static bool ActiveUpdateExpired(DateTimeOffset startedAt)
    {
        return DateTimeOffset.UtcNow - startedAt.ToUniversalTime() > ActiveUpdateTtl;
    }

    private static bool HasActiveUpdateMarker(string installDir)
    {
        var markerPath = ActiveUpdateMarkerPath(installDir);
        if (!File.Exists(markerPath))
        {
            return false;
        }

        var active = ReadActiveUpdateMarker(markerPath);
        return active is not null && !ActiveUpdateExpired(active.Value.StartedAt);
    }

    private static bool IsMutexHeld(string mutexName)
    {
        try
        {
            using var mutex = new Mutex(false, mutexName);
            if (mutex.WaitOne(0, false))
            {
                mutex.ReleaseMutex();
                return false;
            }

            return true;
        }
        catch
        {
            return false;
        }
    }

    private async Task HandleStartupArgumentAsync()
    {
        if (string.IsNullOrWhiteSpace(startupArgument))
        {
            if (IsInstalled(installDirBox.Text.Trim()))
            {
                Log("Installed app detected. Opening GarmentsOS PRO.");
                await OpenAppAsync();
            }
            else
            {
                Log("No installed app detected. Starting fresh install.");
                await InstallAppAsync();
            }
            return;
        }

        if (!Uri.TryCreate(startupArgument, UriKind.Absolute, out var uri) ||
            !string.Equals(uri.Scheme, "garmentsos", StringComparison.OrdinalIgnoreCase))
        {
            Log("Ignoring unsupported startup argument.");
            return;
        }

        var action = string.IsNullOrWhiteSpace(uri.Host)
            ? uri.AbsolutePath.Trim('/')
            : uri.Host;

        if (string.Equals(action, "open", StringComparison.OrdinalIgnoreCase))
        {
            if (!TryAcquireModeMutex(LauncherMode.Open))
            {
                Log("Another GarmentsOS PRO launcher is already opening the app.");
                CloseSoon();
                return;
            }

            Log("Launcher opened from garmentsos://open.");
            await OpenAppAsync();
            return;
        }

        if (!string.Equals(action, "update", StringComparison.OrdinalIgnoreCase))
        {
            Log("Unsupported GarmentsOS protocol action: " + action);
            return;
        }

        var query = ProtocolQuery(startupArgument, uri.Query);
        var request = QueryValue(query, "request");
        startupRequestId = QueryValue(query, "requestId") ?? QueryValue(query, "request_id");
        var autoStart = IsTruthy(QueryValue(query, "autoStart"))
            || IsTruthy(QueryValue(query, "autostart"))
            || IsTruthy(QueryValue(query, "auto"));
        var manualBridge = IsTruthy(QueryValue(query, "manualBridge"))
            || IsTruthy(QueryValue(query, "manualbridge"))
            || IsTruthy(QueryValue(query, "manual"));

        if (!TryAcquireModeMutex(LauncherMode.Update))
        {
            Log("Another GarmentsOS PRO update window is already running.");
            CloseSoon();
            return;
        }

        if (autoStart)
        {
            if (manualBridge)
            {
                EnterManualBridgeMode();
            }
            else
            {
                EnterAutoUpdateMode();
            }
            Log(manualBridge
                ? "Launcher opened from garmentsos://update manual bridge mode."
                : "Launcher opened from garmentsos://update auto-start mode.");
        }
        else
        {
            ConfigureSplashForUpdate();
            Log("Launcher opened from garmentsos://update.");
        }

        if (manualBridge)
        {
            await StartManualBridgeUpdateAsync(autoStart);
            return;
        }

        if (string.IsNullOrWhiteSpace(request))
        {
            if (autoStart)
            {
                ShowFailureMode("Update request was missing from protocol URL.");
                Log("Update request was missing from protocol URL.");
            }
            return;
        }

        currentFeed = null;
        await LoadRequestJsonReferenceAsync(request);

        if (autoStart && currentFeed is not null)
        {
            await UpdateNowAsync(requireConfirmation: false, closeAfterSuccess: true);
        }
    }

    private async Task LoadRequestJsonReferenceAsync(string reference)
    {
        var decoded = Uri.UnescapeDataString(reference.Trim());

        try
        {
            if (Uri.TryCreate(decoded, UriKind.Absolute, out var uri))
            {
                if (uri.Scheme.Equals("http", StringComparison.OrdinalIgnoreCase) ||
                    uri.Scheme.Equals("https", StringComparison.OrdinalIgnoreCase))
                {
                    SetStep("Preparing update...", percent: 0);
                    Log("Loading update request JSON from URL...");
                    using var response = await http.GetAsync(uri);
                    response.EnsureSuccessStatusCode();
                    await LoadRequestJsonAsync(await response.Content.ReadAsStringAsync());
                    return;
                }

                if (uri.Scheme.Equals("file", StringComparison.OrdinalIgnoreCase))
                {
                    await LoadRequestJsonFromFileAsync(uri.LocalPath);
                    return;
                }
            }

            if (File.Exists(decoded))
            {
                await LoadRequestJsonFromFileAsync(decoded);
                return;
            }

            Log("Update request reference was not a supported URL or existing local file.");
            if (autoUpdateMode)
            {
                ShowFailureMode("Update request reference was not a supported URL or existing local file.");
            }
        }
        catch (Exception ex)
        {
            Log("Could not load protocol update request: " + ex.Message);
            if (autoUpdateMode)
            {
                ShowFailureMode(ex.Message);
            }
        }
    }

    private async Task ConfirmAndFreshResetInstallAsync()
    {
        var confirm = MessageBox.Show(
            "This will delete local database, license state, backups, and setup data. Continue with fresh reset/install?",
            "Fresh Reset GarmentsOS PRO",
            MessageBoxButtons.YesNo,
            MessageBoxIcon.Warning,
            MessageBoxDefaultButton.Button2);

        if (confirm != DialogResult.Yes)
        {
            Log("Fresh reset/install cancelled by user.");
            return;
        }

        await InstallAppAsync(freshReset: true);
    }

    private async Task StartManualBridgeUpdateAsync(bool autoStart)
    {
        try
        {
            ConfigureSplashForUpdate();
            SetStep("Preparing manual update...", percent: 10);

            var installDir = installDirBox.Text.Trim();
            var feedUrl = GetConfiguredFeedUrl(installDir);
            var installedVersion = GetInstalledVersion(installDir);

            Log("Manual bridge update requested.");
            Log("Manual bridge feed URL: " + feedUrl);
            Log("Manual bridge installed version: " + installedVersion);

            SetStep("Preparing manual update...", percent: 15);
            currentFeed = await FetchFeedAsync(feedUrl);
            startupRequestId = "manual-bridge-" + Guid.NewGuid().ToString("N");
            startupLockFailedUrl = null;

            WriteManualBridgeLog(installDir, feedUrl, installedVersion, currentFeed);

            if (VersionCompare(currentFeed.Version, installedVersion) <= 0)
            {
                SetStep("Already up to date", percent: 100);
                Log($"Already up to date. Installed={installedVersion}; Latest={currentFeed.Version}");
                ShowFailureMode("Already up to date.", "Already up to date");
                return;
            }

            Log($"Manual bridge update available: {installedVersion} -> {currentFeed.Version}");

            if (autoStart)
            {
                await UpdateNowAsync(requireConfirmation: false, closeAfterSuccess: true);
            }
            else
            {
                updateButton.Enabled = true;
                Log("Manual bridge update is ready. Use Update Now from the context menu if needed.");
            }
        }
        catch (Exception ex)
        {
            Log("Manual bridge update failed: " + ex.Message);
            ShowFailureMode(ex.Message, "Manual update failed");
        }
    }

    private string GetInstalledVersion(string installDir)
    {
        var envVersion = ReadEnvValue(Path.Combine(installDir, ".env"), "APP_VERSION");
        if (!string.IsNullOrWhiteSpace(envVersion))
        {
            return envVersion;
        }

        return ReadInstalledManifest(installDir)?.Version ?? "0.0.0";
    }

    private void WriteManualBridgeLog(string installDir, string feedUrl, string installedVersion, ReleaseFeed feed)
    {
        var logPath = CreateSupportLogPath("launcher-manual-bridge");
        lastSupportLogPath = logPath;

        AppendSupportLog(logPath, "Manual bridge update");
        AppendSupportLog(logPath, "Installed version: " + installedVersion);
        AppendSupportLog(logPath, "Feed URL: " + feedUrl);
        AppendSupportLog(logPath, "Target version: " + feed.Version);
        AppendSupportLog(logPath, "Package URL host: " + SafeHost(feed.PackageUrl));
        AppendSupportLog(logPath, "Package sha256: " + feed.PackageSha256);
        AppendSupportLog(logPath, "InstallDir: " + installDir);

        Log("Manual bridge log saved to: " + logPath);
    }

    private static string SafeHost(string url)
    {
        return Uri.TryCreate(url, UriKind.Absolute, out var uri)
            ? uri.Host
            : "invalid-url";
    }

    private async Task LoadRequestJsonFromFileAsync(string path)
    {
        await LoadRequestJsonAsync(await File.ReadAllTextAsync(path));
        Log("Loaded update request JSON: " + path);
    }

    private Task LoadRequestJsonAsync(string json)
    {
        var request = JsonSerializer.Deserialize<UpdateRequest>(json, jsonOptions)
            ?? throw new InvalidOperationException("Update request JSON could not be read.");

        if (string.IsNullOrWhiteSpace(request.TargetVersion) ||
            string.IsNullOrWhiteSpace(request.PackageUrl) ||
            string.IsNullOrWhiteSpace(request.PackageSha256))
        {
            throw new InvalidOperationException("Update request is missing target_version, package_url, or package_sha256.");
        }

        currentFeed = new ReleaseFeed
        {
            Version = request.TargetVersion,
            Channel = request.Channel,
            PackageFile = request.PackageFile,
            PackageUrl = request.PackageUrl,
            PackageSha256 = request.PackageSha256,
            Mandatory = request.Mandatory,
            Notes = request.Notes,
        };
        startupRequestId = !string.IsNullOrWhiteSpace(request.RequestId)
            ? request.RequestId
            : startupRequestId;
        startupLockFailedUrl = !string.IsNullOrWhiteSpace(request.UpdateLockFailedUrl)
            ? request.UpdateLockFailedUrl
            : startupLockFailedUrl;

        latestVersionLabel.Text = $"Latest version: {currentFeed.Version}";
        mandatoryLabel.Text = $"Mandatory: {currentFeed.Mandatory}";
        notesBox.Text = currentFeed.Notes;
        updateButton.Enabled = !string.IsNullOrWhiteSpace(currentFeed.PackageUrl)
            && !string.IsNullOrWhiteSpace(currentFeed.PackageSha256);

        Log(autoUpdateMode
            ? $"Update request loaded. Starting update to {currentFeed.Version}."
            : "Update request is ready. Review details, then click Update Now.");

        return Task.CompletedTask;
    }

    private async Task UpdateNowAsync(bool requireConfirmation, bool closeAfterSuccess)
    {
        ConfigureSplashForUpdate();

        if (currentFeed is null)
        {
            Log("No update feed/request is loaded.");
            return;
        }

        if (string.IsNullOrWhiteSpace(currentFeed.PackageUrl) || string.IsNullOrWhiteSpace(currentFeed.PackageSha256))
        {
            Log("Update package URL or SHA256 is missing.");
            return;
        }

        if (!TryAcquireModeMutex(LauncherMode.Update))
        {
            Log("Another GarmentsOS PRO update is already running.");
            ShowFailureMode("Another GarmentsOS PRO update is already running. Please use the existing updater window.", "Update already running");
            return;
        }

        if (requireConfirmation)
        {
            var confirm = MessageBox.Show(
                "The Windows updater will apply this update outside the running app. Docker volumes, data, and backups are preserved by the update script.",
                "Apply GarmentsOS PRO Update",
                MessageBoxButtons.OKCancel,
                MessageBoxIcon.Warning);

            if (confirm != DialogResult.OK)
            {
                return;
            }
        }

        if (!TryBeginActiveUpdate(startupRequestId, currentFeed.Version))
        {
            ReleaseModeMutex();
            CloseSoon();
            return;
        }

        updateButton.Enabled = false;
        criticalUpdateStep = true;
        if (autoUpdateMode)
        {
            ControlBox = false;
        }

        try
        {
            var workDir = Path.Combine(Path.GetTempPath(), "GarmentsOSUpdate", DateTime.Now.ToString("yyyyMMdd_HHmmss"));
            Directory.CreateDirectory(workDir);

            var packageFileName = await ResolvePackageFileNameAsync(currentFeed.PackageFile, currentFeed.PackageUrl);
            var packagePath = Path.Combine(workDir, packageFileName);
            Log("Selected update package file name: " + packageFileName);
            Log("Update package path: " + packagePath);
            SetStep("Downloading update package...", percent: 15);
            Log("Downloading update package...");
            var updatePackageSize = await TryGetPackageSizeAsync(currentFeed.PackageUrl);
            if (updatePackageSize.HasValue)
            {
                SetCurrentStep("download_update", "Package size: " + FormatBytes(updatePackageSize.Value), 15);
            }
            await DownloadFileAsync(currentFeed.PackageUrl, packagePath, "Downloading package", "download_update", 15, 40, updatePackageSize);

            SetStep("Verifying update package...", marquee: true);
            Log("Verifying package SHA256...");
            var actualSha = await ComputeSha256Async(packagePath);
            if (!actualSha.Equals(currentFeed.PackageSha256, StringComparison.OrdinalIgnoreCase))
            {
                throw new InvalidOperationException($"SHA256 mismatch. Expected {currentFeed.PackageSha256}, got {actualSha}.");
            }

            SetStep("Preparing update files...", percent: 55);
            var extractDir = Path.Combine(workDir, "extracted");
            Directory.CreateDirectory(extractDir);
            ExtractPackage(packagePath, extractDir);
            var releaseDir = FindReleaseDir(extractDir);

            Log("Running Windows Docker updater...");
            var script = Path.Combine(releaseDir, "scripts", "windows-docker-update.ps1");
            if (!File.Exists(script))
            {
                throw new FileNotFoundException("Update script not found in package.", script);
            }

            SetStep("Creating backup...", marquee: true);
            try
            {
                await RunProcessAsync(
                    "powershell.exe",
                    $"-NoProfile -ExecutionPolicy Bypass -File \"{script}\" -InstallDir \"{installDirBox.Text.Trim()}\" -ReleaseDir \"{releaseDir}\"",
                    releaseDir,
                    line =>
                    {
                        if (line.Contains("backup", StringComparison.OrdinalIgnoreCase))
                        {
                            SetStep("Creating backup...", marquee: true);
                        }
                        else if (line.Contains("docker load", StringComparison.OrdinalIgnoreCase) || line.Contains("Loaded image", StringComparison.OrdinalIgnoreCase))
                        {
                            SetStep("Applying update...", marquee: true);
                        }
                        else if (line.Contains("compose", StringComparison.OrdinalIgnoreCase) || line.Contains("started", StringComparison.OrdinalIgnoreCase))
                        {
                            SetStep("Restarting services...", marquee: true);
                        }
                    });
            }
            catch (Exception updateScriptEx)
            {
                if (IsPreUpdateBackupFailure(updateScriptEx))
                {
                    throw new InvalidOperationException("Pre-update backup failed. Current version is still running. updateApplied=false", updateScriptEx);
                }

                var restartLog = await CaptureRestartServicesDiagnosticsAsync(installDirBox.Text.Trim(), updateScriptEx);
                throw new InvalidOperationException(FriendlyUpdateScriptFailure(updateScriptEx) + " Details were saved to: " + restartLog, updateScriptEx);
            }

            SetStep("Opening app...", percent: 95);
            StartPendingLauncherReplacementHelper(installDirBox.Text.Trim());
            Log("Update complete. Opening app...");
            OpenUrl(AppUrl);
            criticalUpdateStep = false;
            ControlBox = true;
            SetStep("Update complete", percent: 100);
            await RefreshStatusAsync();

            if (closeAfterSuccess && !detailsExpanded)
            {
                var timer = new System.Windows.Forms.Timer { Interval = 4000 };
                timer.Tick += (_, _) =>
                {
                    timer.Stop();
                    Close();
                };
                timer.Start();
            }
        }
        catch (DownloadFailedException ex)
        {
            Log("Update package download failed: " + ex.InnerException?.Message);
            await NotifyUpdateLockFailedAsync();
            criticalUpdateStep = false;
            ControlBox = true;
            updateButton.Enabled = currentFeed is not null;
            ShowFailureMode(ex.Message, "Download failed");
        }
        catch (Exception ex)
        {
            await HandleUpdateFailureAsync(ex);
        }
        finally
        {
            ClearActiveUpdateMarker();
            ReleaseModeMutex();
        }
    }

    private async Task InstallAppAsync(bool freshReset = false)
    {
        try
        {
            ConfigureSplashForInstall();
            failureButtonsPanel.Visible = false;
            logBox.Visible = false;
            detailsExpanded = false;
            detailsButton.Text = "Details";

            var installDir = installDirBox.Text.Trim();
            SetStep("Preparing installation", percent: 10);
            Log(freshReset ? "Starting fresh reset/install flow." : "Starting fresh install flow.");

            await EnsureDockerRunningAsync();

            var feedUrl = GetConfiguredFeedUrl(installDir);
            SetStep("Downloading installation package...", percent: 30);
            Log("Fetching release feed.");
            currentFeed = await FetchFeedAsync(feedUrl);

            var workDir = Path.Combine(Path.GetTempPath(), "GarmentsOSInstall", DateTime.Now.ToString("yyyyMMdd_HHmmss"));
            Directory.CreateDirectory(workDir);

            var packageFileName = await ResolvePackageFileNameAsync(currentFeed.PackageFile, currentFeed.PackageUrl);
            var packagePath = Path.Combine(workDir, packageFileName);
            Log("Selected install package file name: " + packageFileName);
            Log("Install package path: " + packagePath);
            SetStep("Downloading installation package...", percent: 20);
            var installPackageSize = await TryGetPackageSizeAsync(currentFeed.PackageUrl);
            if (installPackageSize.HasValue)
            {
                SetCurrentStep("download_package", "Package size: " + FormatBytes(installPackageSize.Value), 20);
            }
            await DownloadFileAsync(currentFeed.PackageUrl, packagePath, "Downloading package", "download_package", 20, 45, installPackageSize);

            SetStep("Verifying package", percent: 48);
            var actualSha = await ComputeSha256Async(packagePath);
            if (!actualSha.Equals(currentFeed.PackageSha256, StringComparison.OrdinalIgnoreCase))
            {
                throw new InvalidOperationException($"SHA256 mismatch. Expected {currentFeed.PackageSha256}, got {actualSha}.");
            }

            SetStep("Extracting files", percent: 58);
            var extractDir = Path.Combine(workDir, "extracted");
            Directory.CreateDirectory(extractDir);
            ExtractPackage(packagePath, extractDir);
            var releaseDir = FindReleaseDir(extractDir);
            CopyCurrentLauncherIntoRelease(releaseDir);

            var script = Path.Combine(releaseDir, "scripts", "windows-docker-install.ps1");
            if (!File.Exists(script))
            {
                throw new FileNotFoundException("Install script not found in package.", script);
            }

            SetStep("Creating environment", percent: 64);
            await RunProcessAsync(
                "powershell.exe",
                $"-NoProfile -ExecutionPolicy Bypass -File \"{script}\" -InstallDir \"{installDir}\"{(freshReset ? " -FreshReset" : "")}",
                releaseDir,
                line =>
                {
                    if (line.Contains("docker load", StringComparison.OrdinalIgnoreCase) || line.Contains("Loaded image", StringComparison.OrdinalIgnoreCase))
                    {
                        SetStep("Loading Docker image", percent: 74);
                    }
                    else if (line.Contains("compose", StringComparison.OrdinalIgnoreCase) || line.Contains("started", StringComparison.OrdinalIgnoreCase))
                    {
                        SetStep("Starting app services", percent: 84);
                    }
                    else if (line.Contains("Shortcut", StringComparison.OrdinalIgnoreCase) || line.Contains("protocol", StringComparison.OrdinalIgnoreCase))
                    {
                        SetStep("Creating shortcuts", percent: 90);
                    }
                });

            SetStep("Opening app", percent: 95);
            if (!await WaitForAppAsync(TimeSpan.FromSeconds(60)))
            {
                throw new TimeoutException("GarmentsOS PRO installed, but the web app did not respond.");
            }

            OpenUrl(AppUrl);
            SetStep("Ready", percent: 100);
            await Task.Delay(TimeSpan.FromSeconds(2));
            Close();
        }
        catch (DownloadFailedException ex)
        {
            Log("Install package download failed: " + ex.InnerException?.Message);
            ShowFailureMode(ex.Message, "Download failed");
        }
        catch (Exception ex)
        {
            Log("Install failed: " + ex.Message);
            ShowFailureMode(FriendlyInstallError(ex), "Install failed");
        }
    }

    private async Task HandleUpdateFailureAsync(Exception ex)
    {
        Log("Update failed: " + ex.Message);
        await NotifyUpdateLockFailedAsync();
        var rollbackLogPath = CreateRollbackLogPath();
        AppendRollbackLog(rollbackLogPath, "Update failed: " + ex.Message);
        var backupPath = FindLatestValidBackupPath(installDirBox.Text.Trim());

        autoUpdateMode = false;
        criticalUpdateStep = false;
        ControlBox = true;
        updateButton.Enabled = currentFeed is not null;

        if (IsPreUpdateBackupFailure(ex))
        {
            AppendRollbackLog(rollbackLogPath, "Rollback skipped because pre-update backup failed before update was applied.");
            Log("Rollback skipped because pre-update backup failed before update was applied.");
            ShowFailureMode("Pre-update backup failed. Current version is still running.", "Pre-update backup failed");
            await RefreshStatusAsync();
            return;
        }

        SetStep("Restoring previous version...", marquee: true);
        var rollbackOk = await TryRestorePreviousVersionAsync(rollbackLogPath);

        if (rollbackOk)
        {
            Log("Previous version restore/start completed.");
            AppendRollbackLog(rollbackLogPath, "Previous version restore/start completed.");
            ShowFailureMode("Update failed. Previous version was restored. Open details to see the reason.");
        }
        else
        {
            Log("Previous version restore/start could not be completed automatically.");
            AppendRollbackLog(rollbackLogPath, "Rollback failed or backup was not valid.");
            var message = backupPath is null
                ? "Update failed. Backup was not valid. Please contact support."
                : "Rollback could not complete automatically. Backup is available at: " + backupPath;
            ShowFailureMode(message, "Update failed");
        }

        await RefreshStatusAsync();
    }

    private static string FriendlyUpdateScriptFailure(Exception ex)
    {
        var message = ex.ToString();
        if (message.Contains("Post-update asset verification failed", StringComparison.OrdinalIgnoreCase) ||
            message.Contains("public/build/manifest.json verification failed", StringComparison.OrdinalIgnoreCase))
        {
            return "Post-update asset verification failed.";
        }

        if (message.Contains("Migration verification failed", StringComparison.OrdinalIgnoreCase) ||
            message.Contains("Running app container cannot see expected migration", StringComparison.OrdinalIgnoreCase) ||
            message.Contains("does not contain expected migration", StringComparison.OrdinalIgnoreCase))
        {
            return "Post-update migration verification failed.";
        }

        if (message.Contains("APP_VERSION mismatch", StringComparison.OrdinalIgnoreCase))
        {
            return "Post-update version verification failed.";
        }

        if (message.Contains("Storage link verification failed", StringComparison.OrdinalIgnoreCase))
        {
            return "Post-update storage link verification failed.";
        }

        return "Restart services failed.";
    }

    private async Task<bool> TryRestorePreviousVersionAsync(string rollbackLogPath)
    {
        var installDir = installDirBox.Text.Trim();

        if (string.IsNullOrWhiteSpace(installDir) || !Directory.Exists(installDir))
        {
            Log("Rollback skipped because install folder was not found: " + installDir);
            AppendRollbackLog(rollbackLogPath, "Rollback skipped; install folder not found: " + installDir);
            return false;
        }

        var backupPath = FindLatestValidBackupPath(installDir);
        if (backupPath is null)
        {
            Log("Rollback skipped because no valid backup was found.");
            AppendRollbackLog(rollbackLogPath, "Rollback skipped; no valid backup was found.");
            return false;
        }

        AppendRollbackLog(rollbackLogPath, "Backup candidate: " + backupPath);

        var rollbackScripts = new[]
        {
            Path.Combine(installDir, "scripts", "windows-docker-rollback.ps1"),
            Path.Combine(installDir, "scripts", "windows-docker-recover.ps1"),
        };

        foreach (var script in rollbackScripts)
        {
            if (!File.Exists(script))
            {
                continue;
            }

            try
            {
                SetStep("Rolling back to previous version...", marquee: true);
                Log("Running rollback script: " + script);
                AppendRollbackLog(rollbackLogPath, "Running rollback script: " + script);
                await RunProcessAsync(
                    "powershell.exe",
                    $"-NoProfile -ExecutionPolicy Bypass -File \"{script}\" -InstallDir \"{installDir}\"",
                    installDir,
                    onOutput: line => AppendRollbackLog(rollbackLogPath, line),
                    timeout: TimeSpan.FromMinutes(5));

                SetStep("Starting previous version...", marquee: true);
                await RunProcessAsync(
                    ResolveDockerCommand(),
                    "compose up -d",
                    installDir,
                    onOutput: line => AppendRollbackLog(rollbackLogPath, line),
                    timeout: TimeSpan.FromMinutes(5));
                return true;
            }
            catch (Exception rollbackEx)
            {
                Log("Rollback script failed: " + rollbackEx.Message);
                AppendRollbackLog(rollbackLogPath, "Rollback script failed: " + rollbackEx.Message);
                return false;
            }
        }

        try
        {
            // Fallback: even if no rollback script exists, remove update-lock feel and bring the
            // currently installed/previous compose stack back up so the app is accessible.
            SetStep("Starting previous version...", marquee: true);
            Log("No rollback script found. Starting existing compose stack as fallback.");
            AppendRollbackLog(rollbackLogPath, "No rollback script found. Starting existing compose stack as fallback.");
            await RunProcessAsync(
                ResolveDockerCommand(),
                "compose up -d",
                installDir,
                onOutput: line => AppendRollbackLog(rollbackLogPath, line),
                timeout: TimeSpan.FromMinutes(5));
            return true;
        }
        catch (Exception startEx)
        {
            Log("Fallback start failed: " + startEx.Message);
            AppendRollbackLog(rollbackLogPath, "Fallback start failed: " + startEx.Message);
            return false;
        }
    }

    private string CreateRollbackLogPath()
    {
        var installDir = installDirBox.Text.Trim();
        var logDir = Path.Combine(string.IsNullOrWhiteSpace(installDir) ? DefaultInstallDir : installDir, "logs");
        Directory.CreateDirectory(logDir);
        return Path.Combine(logDir, "rollback-" + DateTime.Now.ToString("yyyyMMdd_HHmmss") + ".log");
    }

    private bool IsPreUpdateBackupFailure(Exception exception)
    {
        for (var current = exception; current is not null; current = current.InnerException)
        {
            if (current.Message.Contains("Pre-update backup failed", StringComparison.OrdinalIgnoreCase)
                || current.Message.Contains("Backup did not complete successfully", StringComparison.OrdinalIgnoreCase))
            {
                return true;
            }
        }

        return logBox.Text.Contains("Pre-update backup failed", StringComparison.OrdinalIgnoreCase)
            || logBox.Text.Contains("Backup failed:", StringComparison.OrdinalIgnoreCase)
            || logBox.Text.Contains("Backup did not complete successfully", StringComparison.OrdinalIgnoreCase);
    }

    private static void AppendRollbackLog(string path, string message)
    {
        try
        {
            File.AppendAllText(path, $"[{DateTime.Now:O}] {message}{Environment.NewLine}");
        }
        catch
        {
        }
    }

    private async Task<string> CaptureRestartServicesDiagnosticsAsync(string installDir, Exception exception)
    {
        var logPath = CreateSupportLogPath("restart-services");
        lastSupportLogPath = logPath;

        AppendSupportLog(logPath, "Restart services diagnostics");
        AppendSupportLog(logPath, "InstallDir: " + installDir);
        AppendSupportLog(logPath, "Exception: " + exception.GetType().Name + ": " + exception.Message);
        AppendSupportLog(logPath, "");
        AppendSupportLog(logPath, "[.env update/app fields]");
        foreach (var line in ReadRedactedEnvDiagnostics(Path.Combine(installDir, ".env")))
        {
            AppendSupportLog(logPath, line);
        }

        await CaptureCommandDiagnosticsAsync(logPath, ResolveDockerCommand(), "compose ps", installDir, "docker compose ps");
        await CaptureCommandDiagnosticsAsync(logPath, ResolveDockerCommand(), "compose logs --tail=200", installDir, "docker compose logs --tail=200");

        Log("Restart services diagnostics saved to: " + logPath);
        return logPath;
    }

    private async Task CaptureCommandDiagnosticsAsync(string logPath, string fileName, string arguments, string workingDirectory, string heading)
    {
        AppendSupportLog(logPath, "");
        AppendSupportLog(logPath, "[" + heading + "]");

        try
        {
            var result = await RunProcessCaptureTextAsync(fileName, arguments, workingDirectory, TimeSpan.FromMinutes(2));
            AppendSupportLog(logPath, "Exit code: " + result.ExitCode);
            if (!string.IsNullOrWhiteSpace(result.Output))
            {
                AppendSupportLog(logPath, result.Output.TrimEnd());
            }
            if (!string.IsNullOrWhiteSpace(result.Error))
            {
                AppendSupportLog(logPath, "[stderr]");
                AppendSupportLog(logPath, result.Error.TrimEnd());
            }
        }
        catch (Exception ex)
        {
            AppendSupportLog(logPath, "Diagnostic command failed: " + ex.Message);
        }
    }

    private static IEnumerable<string> ReadRedactedEnvDiagnostics(string envPath)
    {
        if (!File.Exists(envPath))
        {
            yield return ".env not found: " + envPath;
            yield break;
        }

        var allowedPrefixes = new[]
        {
            "APP_VERSION=",
            "GARMENTSOS_IMAGE=",
            "UPDATE_FEED_URL=",
            "UPDATE_CHANNEL=",
            "UPDATE_LAUNCHER_PROTOCOL=",
            "UPDATE_LOCK_TTL_MINUTES=",
        };

        foreach (var line in File.ReadLines(envPath))
        {
            var trimmed = line.Trim();
            if (allowedPrefixes.Any(prefix => trimmed.StartsWith(prefix, StringComparison.OrdinalIgnoreCase)))
            {
                yield return trimmed;
            }
        }
    }

    private string CreateSupportLogPath(string prefix)
    {
        var logDir = SupportLogDir();
        Directory.CreateDirectory(logDir);
        return Path.Combine(logDir, prefix + "-" + DateTime.Now.ToString("yyyyMMdd_HHmmss") + ".log");
    }

    private string SupportLogDir()
    {
        var installDir = installDirBox.Text.Trim();
        return Path.Combine(string.IsNullOrWhiteSpace(installDir) ? DefaultInstallDir : installDir, "logs");
    }

    private void SaveLauncherErrorLog(string title, string message)
    {
        try
        {
            var logPath = CreateSupportLogPath("launcher-error");
            lastSupportLogPath = logPath;
            AppendSupportLog(logPath, title);
            AppendSupportLog(logPath, message);
            AppendSupportLog(logPath, "");
            AppendSupportLog(logPath, logBox.Text);
            Log("Launcher error log saved to: " + logPath);
        }
        catch
        {
        }
    }

    private static void AppendSupportLog(string path, string message)
    {
        try
        {
            File.AppendAllText(path, $"[{DateTime.Now:O}] {message}{Environment.NewLine}");
        }
        catch
        {
        }
    }

    private static string? FindLatestValidBackupPath(string installDir)
    {
        var backupsDir = Path.Combine(installDir, "backups");
        if (!Directory.Exists(backupsDir))
        {
            return null;
        }

        var candidates = Directory.EnumerateFileSystemEntries(backupsDir)
            .Where(path =>
            {
                var name = Path.GetFileName(path);
                return name.StartsWith("backup_", StringComparison.OrdinalIgnoreCase)
                    && !name.StartsWith("cleanup_", StringComparison.OrdinalIgnoreCase)
                    && !name.StartsWith("env_", StringComparison.OrdinalIgnoreCase)
                    && !name.StartsWith("license_state_", StringComparison.OrdinalIgnoreCase)
                    && !name.Contains(".env", StringComparison.OrdinalIgnoreCase);
            })
            .Select(path => new FileSystemInfoWrapper(path))
            .Where(item => item.Exists && item.LengthOrNonEmpty > 0 && IsValidBusinessBackup(item.Path))
            .OrderByDescending(item => item.LastWriteTimeUtc)
            .FirstOrDefault();

        return candidates?.Path;
    }

    private static bool IsValidBusinessBackup(string path)
    {
        if (!Directory.Exists(path))
        {
            return false;
        }

        var metadata = Path.Combine(path, "backup-metadata.json");
        var database = Path.Combine(path, "database.sqlite");
        if (!File.Exists(metadata) || !File.Exists(database))
        {
            return false;
        }

        try
        {
            return new FileInfo(database).Length > 0;
        }
        catch
        {
            return false;
        }
    }

    private sealed class FileSystemInfoWrapper
    {
        public FileSystemInfoWrapper(string path)
        {
            Path = path;
            if (File.Exists(path))
            {
                var file = new FileInfo(path);
                Exists = file.Exists;
                LastWriteTimeUtc = file.LastWriteTimeUtc;
                LengthOrNonEmpty = file.Length;
            }
            else if (Directory.Exists(path))
            {
                var directory = new DirectoryInfo(path);
                Exists = directory.Exists;
                LastWriteTimeUtc = directory.LastWriteTimeUtc;
                LengthOrNonEmpty = directory.EnumerateFileSystemInfos().Any() ? 1 : 0;
            }
        }

        public string Path { get; }
        public bool Exists { get; }
        public DateTime LastWriteTimeUtc { get; }
        public long LengthOrNonEmpty { get; }
    }

    private async Task NotifyUpdateLockFailedAsync()
    {
        if (string.IsNullOrWhiteSpace(startupLockFailedUrl))
        {
            return;
        }

        try
        {
            using var response = await http.GetAsync(startupLockFailedUrl);
            Log($"Update lock failure callback returned HTTP {(int) response.StatusCode}.");
        }
        catch (Exception callbackEx)
        {
            Log("Could not notify app about failed update handoff: " + callbackEx.Message);
        }
    }

    private async Task<ReleaseFeed> FetchFeedAsync(string feedUrl)
    {
        using var response = await http.GetAsync(feedUrl);
        response.EnsureSuccessStatusCode();
        var feed = JsonSerializer.Deserialize<ReleaseFeed>(await response.Content.ReadAsStringAsync(), jsonOptions)
            ?? throw new InvalidOperationException("Update feed JSON could not be read.");

        if (!string.Equals(feed.App, "garmentsos-pro", StringComparison.OrdinalIgnoreCase) && !string.IsNullOrWhiteSpace(feed.App))
        {
            throw new InvalidOperationException("Update feed is for a different app.");
        }

        if (string.IsNullOrWhiteSpace(feed.Version) || string.IsNullOrWhiteSpace(feed.PackageUrl) || string.IsNullOrWhiteSpace(feed.PackageSha256))
        {
            throw new InvalidOperationException("Update feed is missing version, package_url, or package_sha256.");
        }

        return feed;
    }

    private static string FriendlyInstallError(Exception ex)
    {
        return LooksLikeFeedNetworkError(ex)
            ? "Installation package could not be reached. Please check internet connection or update feed URL."
            : ex.Message;
    }

    private static bool LooksLikeFeedNetworkError(Exception ex)
    {
        for (var current = ex; current is not null; current = current.InnerException)
        {
            if (current is HttpRequestException || current is TaskCanceledException)
            {
                return true;
            }

            var message = current.Message;
            if (message.Contains("No such host", StringComparison.OrdinalIgnoreCase)
                || message.Contains("could not be resolved", StringComparison.OrdinalIgnoreCase)
                || message.Contains("Name or service not known", StringComparison.OrdinalIgnoreCase)
                || message.Contains("nodename nor servname", StringComparison.OrdinalIgnoreCase)
                || message.Contains("actively refused", StringComparison.OrdinalIgnoreCase))
            {
                return true;
            }
        }

        return false;
    }

    private async Task<long?> TryGetPackageSizeAsync(string url)
    {
        try
        {
            using var request = new HttpRequestMessage(HttpMethod.Head, url);
            using var response = await http.SendAsync(request, HttpCompletionOption.ResponseHeadersRead);
            return response.IsSuccessStatusCode ? response.Content.Headers.ContentLength : null;
        }
        catch (Exception ex)
        {
            Log("Could not read package size before download: " + ex.Message);
            return null;
        }
    }

    private async Task DownloadFileAsync(string url, string destination, string label, string stepKey, int startPercent, int endPercent, long? expectedBytes = null)
    {
        try
        {
            LogDownloadSource(url);
            using var request = new HttpRequestMessage(HttpMethod.Get, url);
            using var response = await downloadHttp.SendAsync(request, HttpCompletionOption.ResponseHeadersRead);
            var finalUrl = response.RequestMessage?.RequestUri?.ToString() ?? url;
            var contentType = response.Content.Headers.ContentType?.ToString() ?? "";
            var totalBytes = response.Content.Headers.ContentLength ?? expectedBytes;
            var contentDisposition = response.Content.Headers.ContentDisposition?.ToString() ?? "";

            Log($"Download response: status={(int) response.StatusCode} {response.ReasonPhrase}; url={url}; final_url={finalUrl}; content_type={contentType}; content_disposition={contentDisposition}; bytes_expected={(totalBytes.HasValue ? totalBytes.Value.ToString() : "unknown")}");

            if (!response.IsSuccessStatusCode)
            {
                throw new InvalidOperationException(DownloadHttpErrorMessage(url, finalUrl, (int) response.StatusCode, response.ReasonPhrase, contentType));
            }

            await using var source = await response.Content.ReadAsStreamAsync();
            await using var target = File.Create(destination);

            var buffer = new byte[1024 * 256];
            long downloaded = 0;
            var startedAt = DateTimeOffset.UtcNow;
            var lastProgressAt = DateTimeOffset.MinValue;
            var lastByteAt = DateTimeOffset.UtcNow;
            var slowMessageShown = false;

            SetCurrentStep(stepKey, $"{label}: starting...", startPercent);

            while (true)
            {
                var readTask = source.ReadAsync(buffer, 0, buffer.Length);
                while (!readTask.IsCompleted)
                {
                    await Task.WhenAny(readTask, Task.Delay(TimeSpan.FromSeconds(1)));
                    if (!slowMessageShown && (DateTimeOffset.UtcNow - lastByteAt).TotalSeconds >= 20)
                    {
                        var slowMessage = "Download is taking longer than usual. Please keep this window open.";
                        SetCurrentStep(stepKey, slowMessage, Math.Max(progressBar.Value, startPercent));
                        SetStepDetail(slowMessage);
                        Log(slowMessage);
                        slowMessageShown = true;
                    }
                }

                var read = await readTask;
                if (read <= 0)
                {
                    break;
                }

                await target.WriteAsync(buffer.AsMemory(0, read));
                downloaded += read;
                lastByteAt = DateTimeOffset.UtcNow;

                var now = DateTimeOffset.UtcNow;
                if ((now - lastProgressAt).TotalMilliseconds < 500 && totalBytes.HasValue && downloaded < totalBytes.Value)
                {
                    continue;
                }

                lastProgressAt = now;
                UpdateDownloadProgress(label, stepKey, downloaded, totalBytes, startedAt, startPercent, endPercent);
            }

            if (downloaded == 0)
            {
                throw new InvalidOperationException("No bytes were received from the update server.");
            }

            Log($"Download completed: url={url}; final_url={finalUrl}; bytes_downloaded={downloaded}; content_type={contentType}; content_disposition={contentDisposition}");
            UpdateDownloadProgress(label, stepKey, downloaded, totalBytes ?? downloaded, startedAt, endPercent, endPercent);
        }
        catch (Exception ex)
        {
            Log($"Download failed: url={url}; destination={destination}; exception={ex.GetType().Name}; message={ex.Message}");
            throw new DownloadFailedException(ex);
        }
    }

    private static string DownloadHttpErrorMessage(string url, string finalUrl, int statusCode, string? reasonPhrase, string contentType)
    {
        var status = $"{statusCode} {reasonPhrase}".Trim();
        if (statusCode is 401 or 403)
        {
            return $"Download failed with HTTP {status}. The update package may require authentication or the public release asset may not be available yet. URL: {url}. Final URL: {finalUrl}.";
        }

        if (statusCode == 404)
        {
            if (IsGithubUrl(url) || IsGithubUrl(finalUrl))
            {
                return $"GitHub release package was not found or is not public yet. HTTP 404. URL: {url}. Final URL: {finalUrl}.";
            }

            return $"Download failed with HTTP 404. The update package URL was not reachable or the release asset is missing. URL: {url}. Final URL: {finalUrl}.";
        }

        return $"Download failed with HTTP {status}. URL: {url}. Final URL: {finalUrl}. Content-Type: {contentType}.";
    }

    private void LogDownloadSource(string url)
    {
        if (!Uri.TryCreate(url, UriKind.Absolute, out var uri))
        {
            return;
        }

        if (uri.Host.Equals("github.com", StringComparison.OrdinalIgnoreCase))
        {
            Log("Downloading package directly from public GitHub Releases.");
            return;
        }

        if (uri.Host.EndsWith("sparkpair.dev", StringComparison.OrdinalIgnoreCase)
            && uri.AbsolutePath.StartsWith("/api/downloads/", StringComparison.OrdinalIgnoreCase))
        {
            Log("Warning: SparkPair binary proxy URL is deprecated. Feed should point package_url directly to public GitHub Releases.");
        }
    }

    private static bool IsGithubUrl(string value)
    {
        return Uri.TryCreate(value, UriKind.Absolute, out var uri)
            && uri.Host.Equals("github.com", StringComparison.OrdinalIgnoreCase);
    }

    private async Task<string> ResolvePackageFileNameAsync(string? packageFile, string packageUrl)
    {
        var urlFileName = UrlFileName(packageUrl);
        if (!string.IsNullOrWhiteSpace(packageFile) || HasSupportedPackageExtension(urlFileName))
        {
            return ResolvePackageFileName(packageFile, packageUrl, contentDisposition: null);
        }

        var remoteFileName = await TryGetRemotePackageFileNameAsync(packageUrl);
        return ResolvePackageFileName(packageFile, packageUrl, remoteFileName);
    }

    private async Task<string?> TryGetRemotePackageFileNameAsync(string url)
    {
        try
        {
            using var request = new HttpRequestMessage(HttpMethod.Head, url);
            using var response = await downloadHttp.SendAsync(request, HttpCompletionOption.ResponseHeadersRead);
            var finalUrl = response.RequestMessage?.RequestUri?.ToString() ?? url;
            var contentDisposition = response.Content.Headers.ContentDisposition;
            var remoteFileName = contentDisposition?.FileNameStar ?? contentDisposition?.FileName;

            Log($"Package filename probe: status={(int) response.StatusCode} {response.ReasonPhrase}; url={url}; final_url={finalUrl}; content_disposition={contentDisposition}");

            if (!response.IsSuccessStatusCode || string.IsNullOrWhiteSpace(remoteFileName))
            {
                return null;
            }

            return remoteFileName.Trim('"');
        }
        catch (Exception ex)
        {
            Log("Could not read remote package filename before download: " + ex.Message);
            return null;
        }
    }

    private static string ResolvePackageFileName(string? packageFile, string packageUrl, string? contentDisposition)
    {
        var candidate = !string.IsNullOrWhiteSpace(packageFile)
            ? packageFile.Trim()
            : (!string.IsNullOrWhiteSpace(contentDisposition) ? contentDisposition.Trim() : UrlFileName(packageUrl));

        candidate = SanitizePackageFileName(candidate);
        if (string.IsNullOrWhiteSpace(candidate))
        {
            candidate = "garmentsos-pro-package.zip";
        }

        if (!HasSupportedPackageExtension(candidate))
        {
            candidate = StripKnownArchiveExtension(candidate) + ".zip";
        }

        if (!HasSupportedPackageExtension(candidate))
        {
            throw new InvalidOperationException("Unsupported update package format. Use .zip or .tar.gz.");
        }

        return candidate;
    }

    private static string UrlFileName(string packageUrl)
    {
        if (!Uri.TryCreate(packageUrl, UriKind.Absolute, out var uri))
        {
            return "";
        }

        return Path.GetFileName(uri.LocalPath);
    }

    private static string SanitizePackageFileName(string fileName)
    {
        var sanitized = fileName.Trim().Replace('\\', '/');
        sanitized = sanitized.Split('/', StringSplitOptions.RemoveEmptyEntries).LastOrDefault() ?? "";

        foreach (var invalid in Path.GetInvalidFileNameChars())
        {
            sanitized = sanitized.Replace(invalid, '_');
        }

        return sanitized;
    }

    private static bool HasSupportedPackageExtension(string fileName)
    {
        return fileName.EndsWith(".zip", StringComparison.OrdinalIgnoreCase)
            || fileName.EndsWith(".tar.gz", StringComparison.OrdinalIgnoreCase)
            || fileName.EndsWith(".tgz", StringComparison.OrdinalIgnoreCase);
    }

    private static string StripKnownArchiveExtension(string fileName)
    {
        foreach (var extension in new[] { ".tar.gz", ".tgz", ".zip", ".tar", ".gz" })
        {
            if (fileName.EndsWith(extension, StringComparison.OrdinalIgnoreCase))
            {
                return fileName[..^extension.Length];
            }
        }

        return fileName;
    }

    private void UpdateDownloadProgress(string label, string stepKey, long downloadedBytes, long? totalBytes, DateTimeOffset startedAt, int startPercent, int endPercent)
    {
        var elapsedSeconds = Math.Max(0.1, (DateTimeOffset.UtcNow - startedAt).TotalSeconds);
        var speedBytesPerSecond = downloadedBytes / elapsedSeconds;
        var percent = startPercent;
        var message = $"{label}: {FormatBytes(downloadedBytes)} downloaded - {FormatSpeed(speedBytesPerSecond)}";

        if (totalBytes.HasValue && totalBytes.Value > 0)
        {
            var downloadPercent = Math.Clamp(downloadedBytes / (double) totalBytes.Value, 0, 1);
            percent = startPercent + (int) Math.Round((endPercent - startPercent) * downloadPercent);
            var remainingSeconds = speedBytesPerSecond > 1
                ? (totalBytes.Value - downloadedBytes) / speedBytesPerSecond
                : 0;
            message = $"{label}: {FormatBytes(downloadedBytes)} / {FormatBytes(totalBytes.Value)} - {(int) Math.Round(downloadPercent * 100)}% - {FormatSpeed(speedBytesPerSecond)} - {FormatEta(remainingSeconds)} left";
        }

        SetCurrentStep(stepKey, message, Math.Clamp(percent, startPercent, endPercent));
        SetStepDetail(message);
    }

    private static string FormatBytes(long bytes)
    {
        var mb = bytes / 1024d / 1024d;
        return mb >= 1024
            ? $"{mb / 1024d:0.0} GB"
            : $"{mb:0} MB";
    }

    private static string FormatSpeed(double bytesPerSecond)
    {
        var mb = bytesPerSecond / 1024d / 1024d;
        return $"{mb:0.0} MB/s";
    }

    private static string FormatEta(double seconds)
    {
        if (seconds <= 0 || double.IsNaN(seconds) || double.IsInfinity(seconds))
        {
            return "calculating";
        }

        if (seconds < 60)
        {
            return "under 1 min";
        }

        return $"about {Math.Max(1, (int) Math.Ceiling(seconds / 60d))} min";
    }

    private static async Task<string> ComputeSha256Async(string path)
    {
        await using var stream = File.OpenRead(path);
        var hash = await SHA256.HashDataAsync(stream);
        return Convert.ToHexString(hash).ToLowerInvariant();
    }

    private static void ExtractPackage(string packagePath, string extractDir)
    {
        if (packagePath.EndsWith(".zip", StringComparison.OrdinalIgnoreCase))
        {
            ZipFile.ExtractToDirectory(packagePath, extractDir, true);
            return;
        }

        if (packagePath.EndsWith(".tar.gz", StringComparison.OrdinalIgnoreCase) || packagePath.EndsWith(".tgz", StringComparison.OrdinalIgnoreCase))
        {
            var result = Process.Start(new ProcessStartInfo
            {
                FileName = "tar.exe",
                Arguments = $"-xzf \"{packagePath}\" -C \"{extractDir}\"",
                UseShellExecute = false,
                CreateNoWindow = true,
            });
            result?.WaitForExit();
            if (result is null || result.ExitCode != 0)
            {
                throw new InvalidOperationException("tar.exe could not extract the update package.");
            }
            return;
        }

        throw new InvalidOperationException("Unsupported update package format. Use .zip or .tar.gz.");
    }

    private static string FindReleaseDir(string extractDir)
    {
        if (File.Exists(Path.Combine(extractDir, "manifest.json")))
        {
            return extractDir;
        }

        var found = Directory.GetDirectories(extractDir)
            .FirstOrDefault(path => File.Exists(Path.Combine(path, "manifest.json")));

        return found ?? throw new DirectoryNotFoundException("Extracted package does not contain manifest.json.");
    }

    private InstalledManifest? ReadInstalledManifest(string installDir)
    {
        var path = Path.Combine(installDir, "manifest.json");
        if (!File.Exists(path))
        {
            return null;
        }

        try
        {
            return JsonSerializer.Deserialize<InstalledManifest>(File.ReadAllText(path), jsonOptions);
        }
        catch
        {
            return null;
        }
    }

    private static bool IsInstalled(string installDir)
    {
        return File.Exists(Path.Combine(installDir, "manifest.json"))
            && File.Exists(Path.Combine(installDir, "docker-compose.yml"))
            && File.Exists(Path.Combine(installDir, ".env"))
            && File.Exists(Path.Combine(installDir, "scripts", "windows-docker-update.ps1"));
    }

    private string GetConfiguredFeedUrl(string installDir)
    {
        return ReadEnvValue(Path.Combine(installDir, ".env"), "UPDATE_FEED_URL")
            ?? ReadEnvValue(Path.Combine(installDir, ".env"), "UPDATER_MANIFEST_URL")
            ?? DefaultFeedUrl;
    }

    private static void CopyCurrentLauncherIntoRelease(string releaseDir)
    {
        var destination = Path.Combine(releaseDir, PrimaryLauncherExeName);
        if (File.Exists(destination))
        {
            return;
        }

        var current = Environment.ProcessPath;
        if (string.IsNullOrWhiteSpace(current) || !File.Exists(current))
        {
            return;
        }

        File.Copy(current, destination, overwrite: true);
    }

    private static string? ReadEnvValue(string envPath, string key)
    {
        if (!File.Exists(envPath))
        {
            return null;
        }

        foreach (var line in File.ReadLines(envPath))
        {
            var trimmed = line.Trim();
            if (trimmed.StartsWith("#") || !trimmed.StartsWith(key + "=", StringComparison.Ordinal))
            {
                continue;
            }

            return trimmed[(key.Length + 1)..].Trim().Trim('"', '\'');
        }

        return null;
    }

    private async Task<string> GetDockerStatusAsync()
    {
        try
        {
            var result = await RunProcessCaptureAsync(ResolveDockerCommand(), "info", installDirBox.Text.Trim());
            return result == 0 ? "running" : "not available";
        }
        catch
        {
            return "not available";
        }
    }

    private async Task<string> GetAppStatusAsync()
    {
        try
        {
            using var response = await http.GetAsync(AppUrl);
            return response.IsSuccessStatusCode || ((int) response.StatusCode >= 300 && (int) response.StatusCode < 500)
                ? "reachable"
                : "not reachable";
        }
        catch
        {
            return "not reachable";
        }
    }

    private async Task RunInstalledScriptAsync(string relativeScript)
    {
        var script = Path.Combine(installDirBox.Text.Trim(), relativeScript);
        if (!File.Exists(script))
        {
            Log("Script not found: " + script);
            return;
        }

        await RunProcessAsync("powershell.exe", $"-NoProfile -ExecutionPolicy Bypass -File \"{script}\" -InstallDir \"{installDirBox.Text.Trim()}\"", installDirBox.Text.Trim());
    }

    private async Task RunPowerShellAsync(string command, string workingDirectory)
    {
        await RunProcessAsync("powershell.exe", $"-NoProfile -ExecutionPolicy Bypass -Command \"{command}\"", workingDirectory);
    }

    private async Task StopServicesAsync()
    {
        var stopBat = Path.Combine(installDirBox.Text.Trim(), "Stop GarmentsOS.bat");
        if (File.Exists(stopBat))
        {
            await RunProcessAsync("cmd.exe", $"/c \"{stopBat}\"", installDirBox.Text.Trim());
            return;
        }

        await RunPowerShellAsync("docker compose down", installDirBox.Text.Trim());
    }

    private async Task OpenAppAsync()
    {
        if (IsMutexHeld(UpdateMutexName) || HasActiveUpdateMarker(installDirBox.Text.Trim()))
        {
            Log("Open app skipped because an update is already running.");
            ShowFailureMode("GarmentsOS PRO is updating. Please wait for the update to finish.", "Update in progress");
            CloseSoon(1200);
            return;
        }

        if (!TryAcquireModeMutex(LauncherMode.Open))
        {
            Log("Another GarmentsOS PRO launcher is already opening the app.");
            CloseSoon();
            return;
        }

        try
        {
            ConfigureSplashForOpen();
            failureButtonsPanel.Visible = false;
            logBox.Visible = false;
            detailsExpanded = false;
            detailsButton.Text = "Details";

            var installDir = installDirBox.Text.Trim();
            if (!Directory.Exists(installDir))
            {
                throw new DirectoryNotFoundException("Install folder was not found: " + installDir);
            }

            SetStep("Starting GarmentsOS PRO", percent: 10);
            Log("Starting GarmentsOS PRO open flow.");

            await EnsureDockerRunningAsync();

            SetStep("Starting app services", percent: 65);
            Log("Starting app services with docker compose up -d.");
            await RunProcessAsync(ResolveDockerCommand(), "compose up -d", installDir);

            SetStep("Waiting for app", percent: 82);
            Log("Waiting for web app to respond at " + AppUrl + ".");
            if (!await WaitForAppAsync(TimeSpan.FromSeconds(60)))
            {
                throw new TimeoutException("GarmentsOS PRO started, but the web app did not respond.");
            }

            SetStep("Opening app...", percent: 95);
            Log("Opening app in browser: " + AppUrl);
            OpenUrl(AppUrl);

            SetStep("Ready", percent: 100);
            await Task.Delay(TimeSpan.FromSeconds(2));
            Close();
        }
        catch (Exception ex)
        {
            var message = ex.Message.Contains("Docker could not start", StringComparison.OrdinalIgnoreCase)
                ? "Docker could not start. Please open Docker Desktop and try again."
                : ex.Message;
            Log("Open app failed: " + message);
            var actionMode = FailureActionModeFor(message, "Open app failed");
            ShowFailureMode(
                message,
                actionMode == FailureActionMode.Docker ? "Docker needs attention" : "Open app failed",
                actionMode);
        }
        finally
        {
            ReleaseModeMutex();
        }
    }

    private async Task EnsureDockerRunningAsync()
    {
        SetStep("Checking Docker", percent: 20);
        if (await DockerInfoAsync())
        {
            Log("Docker is already running.");
            return;
        }

        SetStep("Starting Docker", percent: 35);
        StartDockerDesktopIfAvailable();

        SetStep("Waiting for Docker", percent: 45);
        if (!await WaitForDockerAsync(TimeSpan.FromSeconds(120)))
        {
            throw new TimeoutException("Docker could not start. Please open Docker Desktop and try again.");
        }

        Log("Docker is running.");
    }

    private async Task<bool> DockerInfoAsync()
    {
        try
        {
            return await RunProcessCaptureAsync(ResolveDockerCommand(), "info", installDirBox.Text.Trim()) == 0;
        }
        catch (Exception ex)
        {
            Log("Docker check failed: " + ex.Message);
            return false;
        }
    }

    private void StartDockerDesktopIfAvailable()
    {
        var candidates = new[]
        {
            @"C:\Program Files\Docker\Docker\Docker Desktop.exe",
        };

        foreach (var candidate in candidates)
        {
            if (!File.Exists(candidate))
            {
                continue;
            }

            Process.Start(new ProcessStartInfo
            {
                FileName = candidate,
                UseShellExecute = true,
                WindowStyle = ProcessWindowStyle.Minimized,
            });
            Log("Docker Desktop start requested: " + candidate);
            return;
        }

        Log("Docker Desktop executable was not found in common install paths.");
    }

    private static string ResolveDockerCommand()
    {
        const string defaultCommand = "docker";
        const string dockerDesktopCli = @"C:\Program Files\Docker\Docker\resources\bin\docker.exe";

        return File.Exists(dockerDesktopCli) ? dockerDesktopCli : defaultCommand;
    }

    private async Task<bool> WaitForDockerAsync(TimeSpan timeout)
    {
        var deadline = DateTimeOffset.UtcNow.Add(timeout);
        while (DateTimeOffset.UtcNow < deadline)
        {
            if (await DockerInfoAsync())
            {
                return true;
            }

            await Task.Delay(TimeSpan.FromSeconds(2));
        }

        return false;
    }

    private async Task<bool> WaitForAppAsync(TimeSpan timeout)
    {
        var deadline = DateTimeOffset.UtcNow.Add(timeout);
        while (DateTimeOffset.UtcNow < deadline)
        {
            if (await IsAppReachableAsync())
            {
                return true;
            }

            await Task.Delay(TimeSpan.FromSeconds(2));
        }

        return false;
    }

    private async Task<bool> IsAppReachableAsync()
    {
        try
        {
            using var response = await http.GetAsync(AppUrl);
            var code = (int) response.StatusCode;
            return code >= 200 && code < 500;
        }
        catch
        {
            return false;
        }
    }

    private async Task<int> RunProcessCaptureAsync(string fileName, string arguments, string workingDirectory)
    {
        var process = StartProcess(fileName, arguments, workingDirectory);
        _ = process.StandardOutput.ReadToEndAsync();
        _ = process.StandardError.ReadToEndAsync();
        await process.WaitForExitAsync();
        return process.ExitCode;
    }

    private async Task<(int ExitCode, string Output, string Error)> RunProcessCaptureTextAsync(string fileName, string arguments, string workingDirectory, TimeSpan timeout)
    {
        var process = StartProcess(fileName, arguments, workingDirectory);
        var outputTask = process.StandardOutput.ReadToEndAsync();
        var errorTask = process.StandardError.ReadToEndAsync();

        var exited = await Task.Run(() => process.WaitForExit((int) timeout.TotalMilliseconds));
        if (!exited)
        {
            try
            {
                process.Kill(entireProcessTree: true);
            }
            catch
            {
            }

            throw new TimeoutException($"{fileName} diagnostics timed out after {timeout.TotalMinutes:0} minutes.");
        }

        return (process.ExitCode, await outputTask, await errorTask);
    }

    private async Task RunProcessAsync(string fileName, string arguments, string workingDirectory, Action<string>? onOutput = null, TimeSpan? timeout = null)
    {
        var process = StartProcess(fileName, arguments, workingDirectory);
        var recentOutput = new List<string>();
        var outputLock = new object();
        void CaptureLine(string line)
        {
            lock (outputLock)
            {
                recentOutput.Add(line);
                if (recentOutput.Count > 120)
                {
                    recentOutput.RemoveAt(0);
                }
            }
        }

        process.OutputDataReceived += (_, e) =>
        {
            if (e.Data is not null)
            {
                CaptureLine(e.Data);
                Log(e.Data);
                onOutput?.Invoke(e.Data);
            }
        };
        process.ErrorDataReceived += (_, e) =>
        {
            if (e.Data is not null)
            {
                CaptureLine(e.Data);
                Log(e.Data);
                onOutput?.Invoke(e.Data);
            }
        };
        process.BeginOutputReadLine();
        process.BeginErrorReadLine();
        if (timeout.HasValue)
        {
            var exited = await Task.Run(() => process.WaitForExit((int) timeout.Value.TotalMilliseconds));
            if (!exited)
            {
                try
                {
                    process.Kill(entireProcessTree: true);
                }
                catch
                {
                }

                throw new TimeoutException($"{fileName} timed out after {timeout.Value.TotalMinutes:0} minutes.");
            }
        }
        else
        {
            await process.WaitForExitAsync();
        }

        if (process.ExitCode != 0)
        {
            string output;
            lock (outputLock)
            {
                output = string.Join(Environment.NewLine, recentOutput);
            }

            var message = $"{fileName} exited with code {process.ExitCode}.";
            if (!string.IsNullOrWhiteSpace(output))
            {
                message += Environment.NewLine + "Recent output:" + Environment.NewLine + output;
            }

            throw new InvalidOperationException(message);
        }
    }

    private static Process StartProcess(string fileName, string arguments, string workingDirectory)
    {
        var process = new Process
        {
            StartInfo = new ProcessStartInfo
            {
                FileName = fileName,
                Arguments = arguments,
                WorkingDirectory = Directory.Exists(workingDirectory)
                    ? workingDirectory
                    : (Directory.Exists(DefaultInstallDir) ? DefaultInstallDir : Environment.CurrentDirectory),
                UseShellExecute = false,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                CreateNoWindow = true,
            },
        };
        process.Start();
        return process;
    }

    private async Task<bool> HandlePendingLauncherUpdateOnStartupAsync()
    {
        var markerPath = Path.Combine(DefaultInstallDir, ".pending-launcher-update.json");
        if (!File.Exists(markerPath))
        {
            return false;
        }

        Log("Pending launcher update detected on startup.");
        AppendPendingLauncherLog(DefaultInstallDir, "Pending launcher update detected on startup.");

        try
        {
            var marker = await ReadPendingLauncherMarkerAsync(markerPath);
            if (string.IsNullOrWhiteSpace(marker.PendingPath) || !File.Exists(marker.PendingPath))
            {
                AppendPendingLauncherLog(DefaultInstallDir, "Pending launcher path missing: " + marker.PendingPath);
                Log("Pending launcher update could not be applied because the pending EXE was not found.");
                return false;
            }

            if (string.IsNullOrWhiteSpace(marker.DestinationPath))
            {
                AppendPendingLauncherLog(DefaultInstallDir, "Pending launcher destination path missing.");
                Log("Pending launcher update could not be applied because the destination path was missing.");
                return false;
            }

            var destinationDir = Path.GetDirectoryName(marker.DestinationPath);
            if (string.IsNullOrWhiteSpace(destinationDir))
            {
                AppendPendingLauncherLog(DefaultInstallDir, "Pending launcher destination folder missing.");
                Log("Pending launcher update could not be applied because the destination folder was missing.");
                return false;
            }

            Directory.CreateDirectory(destinationDir);

            if (IsCurrentProcessPath(marker.DestinationPath))
            {
                AppendPendingLauncherLog(DefaultInstallDir, "Destination launcher is the running process. Starting helper and closing current launcher.");
                StartPendingLauncherReplacementHelper(DefaultInstallDir, relaunchAfterReplace: true, relaunchArgument: startupArgument);
                BeginInvoke(Close);
                return true;
            }

            File.Copy(marker.PendingPath, marker.DestinationPath, overwrite: true);
            File.Delete(marker.PendingPath);
            File.Delete(markerPath);
            RegisterGarmentsProtocol(marker.DestinationPath);
            AppendPendingLauncherLog(DefaultInstallDir, "Pending launcher update applied on startup.");
            Log("Pending launcher update applied on startup.");
            return false;
        }
        catch (Exception ex)
        {
            AppendPendingLauncherLog(DefaultInstallDir, "Startup pending launcher update failed: " + ex.Message);
            Log("Pending launcher update failed on startup: " + ex.Message);
            return false;
        }
    }

    private void StartPendingLauncherReplacementHelper(string installDir, bool relaunchAfterReplace = false, string? relaunchArgument = null)
    {
        var markerPath = Path.Combine(installDir, ".pending-launcher-update.json");
        if (!File.Exists(markerPath))
        {
            return;
        }

        try
        {
            Log("Pending launcher update detected.");
            Log("Launcher update will finish after this updater window closes.");
            var helperPath = Path.Combine(Path.GetTempPath(), "GarmentsOSPendingLauncherUpdate.ps1");
            var relaunchArgumentBase64 = Convert.ToBase64String(Encoding.UTF8.GetBytes(relaunchArgument ?? ""));
            var script = """
                param(
                    [int]$ParentPid,
                    [string]$MarkerPath,
                    [bool]$RelaunchAfterReplace = $false,
                    [string]$RelaunchArgumentBase64 = ""
                )

                $ErrorActionPreference = "Stop"

                function Write-GarmentsPendingLog($Message) {
                    try {
                        $installDir = Split-Path -Parent $MarkerPath
                        $logPath = Join-Path $installDir "pending-launcher-update.log"
                        "[$(Get-Date -Format o)] $Message" | Add-Content -Path $logPath
                    } catch {
                    }
                }

                try {
                    Write-GarmentsPendingLog "helper started"
                    Write-GarmentsPendingLog "waiting for parent: $ParentPid"
                    Wait-Process -Id $ParentPid -ErrorAction SilentlyContinue
                    Write-GarmentsPendingLog "parent exited"
                    Start-Sleep -Seconds 1

                    if (-not (Test-Path -LiteralPath $MarkerPath)) {
                        Write-GarmentsPendingLog "marker not found; nothing to replace"
                        exit 0
                    }

                    $marker = Get-Content -LiteralPath $MarkerPath -Raw | ConvertFrom-Json
                    Write-GarmentsPendingLog "pending path: $($marker.pending_path)"
                    Write-GarmentsPendingLog "destination path: $($marker.destination_path)"

                    if (-not (Test-Path -LiteralPath $marker.pending_path)) {
                        throw "Pending launcher EXE not found: $($marker.pending_path)"
                    }

                    $destinationDir = Split-Path -Parent $marker.destination_path
                    if (-not (Test-Path -LiteralPath $destinationDir)) {
                        New-Item -ItemType Directory -Force -Path $destinationDir | Out-Null
                    }

                    Copy-Item -LiteralPath $marker.pending_path -Destination $marker.destination_path -Force
                    Write-GarmentsPendingLog "copy success"
                    Remove-Item -LiteralPath $marker.pending_path -Force -ErrorAction SilentlyContinue
                    Write-GarmentsPendingLog "pending file removed"
                    Remove-Item -LiteralPath $MarkerPath -Force -ErrorAction SilentlyContinue
                    Write-GarmentsPendingLog "marker removed"

                    $launcher = $marker.destination_path
                    $baseKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos")
                    $baseKey.SetValue("", "URL:GarmentsOS PRO Launcher")
                    $baseKey.SetValue("URL Protocol", "")
                    $baseKey.Close()

                    $iconKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos\DefaultIcon")
                    $iconKey.SetValue("", "$launcher,0")
                    $iconKey.Close()

                    $commandKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos\shell\open\command")
                    $commandKey.SetValue("", "`"$launcher`" `"%1`"")
                    $commandKey.Close()
                    Write-GarmentsPendingLog "protocol registered"

                    if ($RelaunchAfterReplace) {
                        $relaunchArgument = ""
                        if (-not [string]::IsNullOrWhiteSpace($RelaunchArgumentBase64)) {
                            $relaunchArgument = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($RelaunchArgumentBase64))
                        }

                        Write-GarmentsPendingLog "relaunch requested"
                        if ([string]::IsNullOrWhiteSpace($relaunchArgument)) {
                            Start-Process -FilePath $launcher -WorkingDirectory (Split-Path -Parent $launcher)
                        } else {
                            Start-Process -FilePath $launcher -ArgumentList @($relaunchArgument) -WorkingDirectory (Split-Path -Parent $launcher)
                        }
                    }
                } catch {
                    Write-GarmentsPendingLog "error: $($_.Exception.Message)"
                }
                """;
            File.WriteAllText(helperPath, script, Encoding.UTF8);
            Process.Start(new ProcessStartInfo
            {
                FileName = "powershell.exe",
                Arguments = $"-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \"{helperPath}\" -ParentPid {Environment.ProcessId} -MarkerPath \"{markerPath}\" -RelaunchAfterReplace ${relaunchAfterReplace.ToString().ToLowerInvariant()} -RelaunchArgumentBase64 \"{relaunchArgumentBase64}\"",
                UseShellExecute = false,
                CreateNoWindow = true,
            });
            Log("Pending launcher replacement helper started.");
        }
        catch (Exception ex)
        {
            Log("Could not start pending launcher replacement helper: " + ex.Message);
        }
    }

    private sealed class PendingLauncherMarker
    {
        public string PendingPath { get; init; } = "";
        public string DestinationPath { get; init; } = "";
    }

    private static async Task<PendingLauncherMarker> ReadPendingLauncherMarkerAsync(string markerPath)
    {
        await using var stream = File.OpenRead(markerPath);
        using var document = await JsonDocument.ParseAsync(stream);
        var root = document.RootElement;

        return new PendingLauncherMarker
        {
            PendingPath = root.TryGetProperty("pending_path", out var pendingPath) ? pendingPath.GetString() ?? "" : "",
            DestinationPath = root.TryGetProperty("destination_path", out var destinationPath) ? destinationPath.GetString() ?? "" : "",
        };
    }

    private static bool IsCurrentProcessPath(string path)
    {
        try
        {
            var current = Environment.ProcessPath;
            return !string.IsNullOrWhiteSpace(current)
                && string.Equals(Path.GetFullPath(current), Path.GetFullPath(path), StringComparison.OrdinalIgnoreCase);
        }
        catch
        {
            return false;
        }
    }

    private static void RegisterGarmentsProtocol(string launcherPath)
    {
        using var baseKey = Microsoft.Win32.Registry.CurrentUser.CreateSubKey(@"Software\Classes\garmentsos");
        baseKey?.SetValue("", "URL:GarmentsOS PRO Launcher");
        baseKey?.SetValue("URL Protocol", "");

        using var iconKey = Microsoft.Win32.Registry.CurrentUser.CreateSubKey(@"Software\Classes\garmentsos\DefaultIcon");
        iconKey?.SetValue("", $"{launcherPath},0");

        using var commandKey = Microsoft.Win32.Registry.CurrentUser.CreateSubKey(@"Software\Classes\garmentsos\shell\open\command");
        commandKey?.SetValue("", $"\"{launcherPath}\" \"%1\"");
    }

    private static void AppendPendingLauncherLog(string installDir, string message)
    {
        try
        {
            Directory.CreateDirectory(installDir);
            File.AppendAllText(
                Path.Combine(installDir, "pending-launcher-update.log"),
                $"[{DateTime.Now:O}] {message}{Environment.NewLine}");
        }
        catch
        {
        }
    }

    private void SaveLog()
    {
        using var dialog = new SaveFileDialog
        {
            Title = "Save GarmentsOS update log",
            Filter = "Text files (*.txt)|*.txt|All files (*.*)|*.*",
            FileName = "garmentsos-update-log.txt",
        };

        if (dialog.ShowDialog(this) == DialogResult.OK)
        {
            File.WriteAllText(dialog.FileName, logBox.Text);
        }
    }

    private static int VersionCompare(string left, string right)
    {
        return Version.TryParse(left, out var l) && Version.TryParse(right, out var r)
            ? l.CompareTo(r)
            : string.Compare(left, right, StringComparison.OrdinalIgnoreCase);
    }

    private static bool IsTruthy(string? value)
    {
        return string.Equals(value, "1", StringComparison.OrdinalIgnoreCase)
            || string.Equals(value, "true", StringComparison.OrdinalIgnoreCase)
            || string.Equals(value, "yes", StringComparison.OrdinalIgnoreCase)
            || string.Equals(value, "on", StringComparison.OrdinalIgnoreCase);
    }

    private static string ProtocolQuery(string rawArgument, string parsedQuery)
    {
        var questionIndex = rawArgument.IndexOf('?');
        if (questionIndex < 0 || questionIndex == rawArgument.Length - 1)
        {
            return parsedQuery;
        }

        return rawArgument[(questionIndex + 1)..]
            .Trim()
            .Trim('"')
            .Replace("&amp;", "&", StringComparison.OrdinalIgnoreCase);
    }

    private static string? QueryValue(string query, string key)
    {
        var trimmed = query.TrimStart('?');
        if (string.IsNullOrWhiteSpace(trimmed))
        {
            return null;
        }

        foreach (var pair in trimmed.Split('&', StringSplitOptions.RemoveEmptyEntries))
        {
            var parts = pair.Split('=', 2);
            if (parts.Length == 2 && string.Equals(Uri.UnescapeDataString(parts[0]), key, StringComparison.OrdinalIgnoreCase))
            {
                return Uri.UnescapeDataString(parts[1]);
            }
        }

        return null;
    }

    private void Log(string message)
    {
        if (InvokeRequired)
        {
            BeginInvoke(() => Log(message));
            return;
        }

        logBox.AppendText($"[{DateTime.Now:HH:mm:ss}] {message}{Environment.NewLine}");
    }

    private static void OpenUrl(string url)
    {
        Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
    }

    private static void OpenFolder(string path)
    {
        Directory.CreateDirectory(path);
        Process.Start(new ProcessStartInfo(path) { UseShellExecute = true });
    }

    private void OpenSupportFolder()
    {
        OpenFolder(SupportLogDir());
    }

    private void SetRoundedRegion()
    {
        if (Width <= 0 || Height <= 0)
        {
            return;
        }

        using var path = RoundedRect(new Rectangle(0, 0, Width, Height), 22);
        Region = new Region(path);
    }

    private void BeginDrag(MouseEventArgs e)
    {
        if (e.Button != MouseButtons.Left || criticalUpdateStep)
        {
            return;
        }

        ReleaseCapture();
        SendMessage(Handle, 0xA1, 0x2, 0);
    }

    private static GraphicsPath RoundedRect(Rectangle rect, int radius)
    {
        var path = new GraphicsPath();
        var d = Math.Max(1, radius * 2);
        path.AddArc(rect.Left, rect.Top, d, d, 180, 90);
        path.AddArc(rect.Right - d, rect.Top, d, d, 270, 90);
        path.AddArc(rect.Right - d, rect.Bottom - d, d, d, 0, 90);
        path.AddArc(rect.Left, rect.Bottom - d, d, d, 90, 90);
        path.CloseFigure();
        return path;
    }

    [DllImport("user32.dll")]
    private static extern bool ReleaseCapture();

    [DllImport("user32.dll")]
    private static extern IntPtr SendMessage(IntPtr hWnd, int msg, int wParam, int lParam);

    [DllImport("user32.dll")]
    private static extern bool SetForegroundWindow(IntPtr hWnd);

    [DllImport("user32.dll")]
    private static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);
}

internal sealed class UpdaterSplashView : Control
{
    private static readonly Color BrandBlue = Color.FromArgb(37, 99, 235);
    private static readonly Color AppBackground = Color.FromArgb(238, 241, 244);
    private static readonly Color ShellBorder = Color.FromArgb(154, 169, 188);
    private static readonly Color HeaderBorder = Color.FromArgb(214, 221, 231);
    private static readonly Color WindowBorder = Color.FromArgb(135, 152, 173);
    private static readonly Color WindowSoftBorder = Color.FromArgb(174, 185, 200);
    private static readonly Color TextPrimary = Color.FromArgb(7, 16, 31);
    private static readonly Color TextMuted = Color.FromArgb(52, 67, 90);
    private static readonly Color TextHint = Color.FromArgb(108, 122, 144);
    private static readonly Color Track = Color.FromArgb(220, 226, 237);
    private static readonly Color GridLine = Color.FromArgb(4, 37, 99, 235);

    private const string EmbeddedLogoPngBase64 = "iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAABmJLR0QA/wD/AP+gvaeTAAAPdklEQVR4nO2de3Bc1X3Hv9+zu7Ik28LWy8YvhL2S7JpHDSTh2TGFwBBgMgFcah6uWckCnBoSSDIxLVjMBJO06VBKSw1IsssrkzGQJkwpDCklIWOSFgKYUGJp/QA7xtpdyZYA25L2nm//EDjWy16t7u7dK+1nRv/cxzlf7fnu3Xt/95zfj8iTVRau6C7rCx1eHgC/JOIkCJUSphugWEQxhaSoHoAfS+qk4T7C7BD0kor2/0f0oeoeN/XQzcayTfimjypkAmcCCAOAGNor4c2dzdM/8FjaQBplqvd0/hXkXA1xCYhZabRiCURF+ybBf21tqnzNDWm+M0BNfewCiCsEniJoAYGKgUfwAKld1morxAejGyt+641SABCr6+K3QbgeBksgBt1pFt2G+rVl4IdtTWUvj6UpXxig9tZ4re3jNwB9UcAiCkWpnCdyPy1eleFt0aayPZnWeTS1de3nWZj1gM4FXBr4IfAAgZf76KzZ2TSjPa0W3JbkFksbFdz7YedNgv0LkGcAKk27MSJqZO7a1ly22UWJIyBWRxL3gqgHcGLm+wMItErmrraWsmfTODe3mNWwt3iyU7AW1BUQTgUQcKdlxQnT2Npc/rA77Q0lvKZtkjk07ceSLs/ct34k2Enon1ubK9aN6qxMyRktsxr2Fk+xwbslfhXAoox0IsQcY2/d0TTjObebXrw6NqW3h88DWOp226ki4hDFTW3N5atTPcd7AyxTIFzS8S0CNwJanIUet/HgpPNbf1SScKvBxctU0FOSeJHAhW61mTZEH6ANbU2Vt6VyuMm0nmMRrotfWV3S8StC92Vp8AGgVsU9D7rZYG9Jx49yYvABQAhBjNRG4t9O5XBPrgDhmz6qIAMPA7wEREm2+xfUnnRCZ+/aNH3XWNuqjsTvhsFdEApdkOYaBD6Co2tbNx07XpD1K8CCSHyVMcFfgbzGi8EHAIIzQsb55ljbWXTLvlNA3JJrgw8AAk5U0PzD4mUqONZxWbtTDV/fUcJJagZ1mYDJ2ep3JEgtGVsLotObeDDNqF52kM7qKUncD+DOkQ7JyhVgYX18qSm0r4G6Bjkw+AAg4qSqlTvT/ubWRmLXyfA8NzVlABK6qmZ5d/lIB2TcADX1ie9a8WkBp2W6r9Egi4qCgqlV6Z5vjVkFaZKLkjIEq2xx770j7c2YAc5sUKimPvZvFrpHUFYiYqOBRJGVZqRz7sL6/Utl8UW3NWUKUn8eXtM2rFkzYoDw9R0l3U7iPyVzY6pxey8wjg2lc56DZAOZu//XEIQa8+m0FcPtct0Ac1YkZnOS/TmAiwB5H2hym0YZiKd7LWOUGECXjbDDPcINBxYUh/QCiC+42W4uUftR4gzInuy1jtEimho0ash4u2aA6tV7F8Hp+2mu3ey5jZK8CqR/Lv+fIejk8M7OIe9YXDHAopWxavQUbCaQrXCuZ1hortca0oFAMYP2/MHbx2yAOSsSs50An8liLN9TCFZ6rSFtyCFjNKZIYG0kPtVSz473y/7REJosr0Wki4YG4dK/AixTwJLPAPjSWDT5kJyL+6eOhmhP2wDhksQGUBePTZD/kHJgDkW6DKM9LQNUr4rdTmg55O18Am+Qq/PyswsPD94y6gGsjcTPgsW3AObES52sY8ynXktIF8EeHLxtVAaY1bC32CE2AJzjniyfYRn3WkK6kGgbvG1UBphiQ48QONM9ST6E9g9eS0gHUj0Cfjl4e8oGqF4Zv07CV92V5T/o6GcUe73WMVok7op2V2wdvD0lA9RG4lMR0FoAU11X5jNmVVW8bo12ea1j9LAVm+kM3pqSASz1EMBT3BflP15tZJLiu17rGC2UXh1u+3ENUBPpPB/AFW4L8jOGgSf89DNAYnthn/PIcPuOYwBRcL4HsCwTwvzKtjnTngfxltc6UsUKW7Y+MXPYx9djGiBc11kHg7MzI8vHNNJa2icAJL2WcjwI7HMQvHuk/SMaYGmjggb2Zgg+mPiYfaJdFRtE/o/XOo6HhJ8dK2HGiAb4w+7ENwSOce78OGYzHcquBdThtZSRkPRuUZ9zx7GOGd4AyxSQtByQS0uzxydtzZW/tDBPAhjyeOU1BDtp1DjSb//nDGuAcEnsZtD4beKjJ2zvLrsTxH97rWMg6hPU1JbCMvhhDWBgljP/7U+NzXR0yFwN8nWvpXyGJfhsW3P5d1M5eIgBquvbv2yBM9zXNX6JPlXW3VdgryL4hrdKmBT5XGt3+Q0AU5q4NPQKIN5CoNh1beOcXQ9X7juI0KUQfgHAi1ljByVtjM4pu3a4kO9IDDBA1cr90whO7Ld9Y2BP8wmds+eVXyyrfwHRlbWOhd2y+NtoS0UDGmlHc+oAAwRN32oBJ7mrbmLxaiOT0Y2Va+iYm6UMRwuJwwT/y9L5SnRjxQPpNDHAADTMjTQn44DWjWU/TtpPzoXw9wC2udx8H8DfWIvbWpvLL97ePPN36TZ0ZJJg1crYzKDhOyT8O+99lFD2y60tM36e6X5Ou3Hf5MMFge+AukiWp6abGYXAPgBvWZhnonNLN432cj8cR9YFBANq8PWihxzms2DMOgDraus7T7NybgSwSGCVgcosUMHB+RCJHggxEHGK2yG87RQkm7ZvmBlzU9sRAxDGN+vd/cy2ptKtAI5k8KpauX9aKNRXJYfzQJVD7AnKJJJB7EFRZ9Tt7OCDCQL9GS55EAt9u+LFx+zaNP0AgLc/+8s6BgDMxydcZIUqLwTk8RYDANbgctKtnLx5/ET/YyA532MdeTzCACLBeV4LyeMN5uS6A/NAzfZaSB5vCAaUPB/ACV4LyeMNBjT5lz8TGEPamV6LyOMdBmD6tXjy+J4ggOlei5hoLF4dm9KXNHOsNEM2mJHPP0B19Cb57meRxhEJwqrEx0lPcp7Fq2NTenrM1wBdQPAkAbN6e1QqaTqJImZobYkEGwqgvaYutgPEa/ZQ4P7oU2Xdg48LipiSH393mbNsd1Hh1MKbAV3S18M/ATCPAI9O0sPMf+gGwIkCT4RwHgvt16rrYw+3NVX+09EHBQkV5kLtqPHAgps65gZo14G4QEA1QAoAPZkiOIRagd+vicSWtM6rqPt8LkEQYH7p1xhZuKK7zAkd/jvQXiohZ4NqFIpEc0N4dyIZBVYBgBGUVsr0PP3U1MfvdEI9rwOMIIcH/48oSGh5uC5eDwBBI5pxmNQ941Stjs0M9WKTLC8Ej12YKffgZEPdVrVy55MGzN8AjJZwpOOSUA9fgXip/wa/H4mnBANTbzcWyrmFjblMuD5xK43dhEyVt80epHBhkGAvfJ3/NnvURuLflrRW4yV4Ri0wEoakD80zlHAkfocl/mbcDH4/5YYmb4DjUbMy/pek1mKcvTanUGxg+YnXQnKZhQ37TlUQ6wGOWHzRtxA0oB0SH87TT3hN2yQnGXgMgu+KRKWKAdjptYhchZ+W/CPonwKR6WAE5GySIy9ZGGk/B+DVGOcvSowB93otIhdJIvA9kBVe68g0xkpb4E1Gi5xlQX3sWhid47WObGBMsG+LBN8WQcgEAcubc7nmsZuY1kdnJUj6sghCJghH2s8RcJbXOrKFAQASuzzWkTPQmK+DE6cuQv/aQOt6ChNfcmaDQrATqyROvwECeA7QhA8Jd9mOr4AIe60jmxgAaJ1d9qZgtnstxmsMdAXGWE7Xb/RfARppSbzvsRbPseICrzVkmyNp4qy1Q0qKTSiWKUBqwi2TP2IABYOPA9jtoRZPmT85Pp/ADK91ZJsjBtjxaGkXpHe8FOMlJojTJUzxWke2GZApVAYvYoKGhQ1MjdcavGCAAQ539bQAiHqkxVMETfNagxcMMMCezXMPAfhfj7R4imQm1OPf5wypFyA6Dwn62AsxXkLZcf3efySGGCDaNPPX8LzyRZ5sMULZOPMkfFAUMc/YGdYA0bmlm/xUGjVP+gx/BWikNcDTQOq1Z/L4kxErh27rKn+I0m+zKSZP9hm5ePRmOpZmA4iM5qvP4y3HrB4ebS5rkbglW2LyZJ9jGgAArPQdAe3ZEJMn+xzXADtaKt4w5DOYoO8IxjvHNQAA2KL9dwKckCHi8U5KBog+VN0TMM43KezLtKA82SUlAwDA7x+bsUWwjwHqy6SgPNklZQMAQFtL5TqQL2dKTJ7sMyoDAFTPwdANECbszKHxxigNAHz49LT9BqgX+EEmBOXJLqM2AABsa6l4gzB3AcznFvA5aRkAANqaS5+21j4A4FMX9eTJMmkbAAC2b6y8D2IzkH9f4FfGZAAAaGspv13AEwDyj4c+ZMwGAIBoc3kDwafyMQL/4YoBAKq1uSwi8nHkfw58gwjrkgEAgIo2VdQL5mFAOX9jKAD2qKVgNKbYQzneIHVnZCp0TV1srWDuBFSWifbdQsCbku4OMDBbcNYCE6uINqGtGZsLX13XeZ1g1xM6KVN9uAMFTMySGQSezOg/XhuJn2WBJhCnZ7KfPGlAJCRd4uI9wFC2tVS80WeDSwG9ACi/ziB3kISfRJsr38rSpU+srk/cK2EVgXytYo+R8Mqkj8sve28ze7P621db136eZeABSF/IZr9+hOR+K31qgIMCHIIHQRQICkIsAm0xxekCUq/6Rhwm+NInpve6vY/OOti/KcuE17RN4sFpD0C6ZiLk4k0FQXHCRCXtAO12WGwRgv9X+Elp+3ub2TvcOYvrukr7dHiuYM4ReWr/zTbnA5oPYGAtSOkQaN4BtKmtueKRo3d5dve7cFX7udaaHwg6G+DEW5pNxGD1lmh+4dBp2dk0Y+wzrxtlFn7QebYN6HIrVQCAAROyyZ+2bZz5m+FleEmjTPXuxB0AIvB/Fa7UEH4Pw1cdY+7f8Wjph17LyYnn39pIfKpDrjfAlcr5uEG68HcA/r2oN/n9rU/MzJlIaU4Y4HNOrm+fEYK5D8LFAsaJEfg+qGf6kp+s37Xp5JzLxppTBvicmoa95dYpWEfgIkALkaM6j0ObhOeL+5x7cukbP5ic/mCrVu4sDAWm/DWEKwH8KYgSrzUdDwKtEl90guaeHY+Wdnmt53jktAGOJlwXW2LEr8uYMwm7SELulL2neiC+I+GF4j7nh7n8jR+MbwzwR8TaSGKpgOsFnEKyRpAH1TzZC2k7DN42AfP4tkdKX+p/seQvfGiAgcxv6JxnkvY6Y7REQhXAOQBmAnL5PYcOA9xDcpeV2kK0P3m/q/IVbPZ3FhXfG2Aw4es7SgKFzrkC/gzgHAFlAEtBTQMwhVZFACfJoBA6khq+j0KPpQ4RPCypi+QBgQmSMUjbkzQv7uya/p7fB3ww/w+OO1nN7/WXOQAAAABJRU5ErkJggg==";

    public Image? LogoImage { get; set; }
    public string ProgressText { get; set; } = "Opening GarmentsOS PRO...";
    public string InstalledVersionText { get; set; } = "Installed version: unknown";
    public int ProgressPercent { get; set; } = 0;
    public bool IsIndeterminate { get; set; }
    public string? ErrorText { get; set; }
    public string BadgeText { get; set; } = "Secure Launcher";
    public string TitleLine1 { get; set; } = "Starting";
    public string TitleLine2 { get; set; } = "GarmentsOS PRO";
    public string Subtitle { get; set; } = "Ready to start, install, or update.";
    public string HelperText { get; set; } = "";
    public List<LauncherStep> Steps { get; set; } = new();

    private readonly Font brandFont = new("Segoe UI", 10.5f, FontStyle.Bold);
    private readonly Font secureFont = new("Segoe UI", 8.2f, FontStyle.Bold);
    private readonly Font titleFont = new("Segoe UI", 24f, FontStyle.Regular);
    private readonly Font bodyFont = new("Segoe UI", 9.4f, FontStyle.Regular);
    private readonly Font pillFont = new("Segoe UI", 8.2f, FontStyle.Bold);
    private readonly Font smallBoldFont = new("Segoe UI", 8.2f, FontStyle.Bold);
    private readonly Font footerFont = new("Segoe UI", 8.3f, FontStyle.Regular);
    private Image? embeddedLogo;

    public UpdaterSplashView()
    {
        DoubleBuffered = true;
        BackColor = AppBackground;
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw | ControlStyles.UserPaint, true);
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e);

        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.TextRenderingHint = System.Drawing.Text.TextRenderingHint.ClearTypeGridFit;

        g.Clear(AppBackground);

        // Same as HTML .shell: 720x420, white, 1px #9aa9bc, radius 20.
        var shell = new Rectangle(0, 0, Width - 1, Height - 1);
        using (var shellPath = SplashRoundedRect(shell, 20))
        using (var shellBrush = new SolidBrush(Color.White))
        using (var shellPen = new Pen(ShellBorder, 1f))
        {
            g.FillPath(shellBrush, shellPath);
            g.DrawPath(shellPen, shellPath);
        }

        // Header 72px.
        using (var sepPen = new Pen(HeaderBorder, 1f))
        {
            g.DrawLine(sepPen, shell.Left, 72, shell.Right, 72);
        }

        // Subtle grid only inside main section, same as .main:before.
        DrawMainGrid(g, new Rectangle(40, 72, 640, 242));

        DrawHeader(g);
        DrawMainContent(g);
        DrawWindowStack(g);
        DrawBottom(g);
    }

    private void DrawHeader(Graphics g)
    {
        // Header padding 40, logo 26 box with 22 image centered.
        DrawLogo(g, 42, 25, 22);
        DrawText(g, "GarmentsOS PRO", brandFont, BrandBlue, 78, 27);

        FillCircle(g, BrandBlue, 582, 39, 7);
        DrawText(g, BadgeText, secureFont, TextPrimary, 600, 35);
    }

    private void DrawMainContent(Graphics g)
    {
        // .main padding left 40 + .left padding-left 14.
        var leftX = 54;

        DrawText(g, TitleLine1, titleFont, Color.Black, leftX, 107);
        DrawText(g, TitleLine2, titleFont, BrandBlue, leftX, 145);

        DrawText(g, Subtitle, bodyFont, TextMuted, leftX, 205);
        DrawStepList(g, leftX, 245);
    }

    private void DrawWindowStack(Graphics g)
    {
        // Same HTML layout:
        // .right transform translate(-22,16), stack front left=0 top=32.
        var rightX = 418;
        var rightY = 120;

        DrawWindowFrame(g, new Rectangle(rightX + 32, rightY + 0, 206, 124), 12, 0.50f, false);
        DrawWindowFrame(g, new Rectangle(rightX + 16, rightY + 16, 206, 124), 12, 0.74f, false);
        DrawWindowFrame(g, new Rectangle(rightX + 0, rightY + 32, 206, 124), 12, 1f, true);
    }

    private void DrawStepList(Graphics g, int x, int y)
    {
        if (Steps.Count == 0)
        {
            return;
        }

        const int rowHeight = 19;
        const int columnWidth = 170;
        var rowsPerColumn = Steps.Count <= 4 ? Steps.Count : 3;

        for (var i = 0; i < Steps.Count; i++)
        {
            var column = i / rowsPerColumn;
            var row = i % rowsPerColumn;
            DrawStep(g, Steps[i], x + (column * columnWidth), y + (row * rowHeight));
        }
    }

    private void DrawStep(Graphics g, LauncherStep step, int x, int y)
    {
        var markerRect = new Rectangle(x, y + 3, 11, 11);
        var color = step.State switch
        {
            LauncherStepState.Current => BrandBlue,
            LauncherStepState.Done => BrandBlue,
            LauncherStepState.Failed => Color.FromArgb(185, 28, 28),
            _ => Color.FromArgb(150, 163, 184),
        };

        using var pen = new Pen(color, 1.7f);
        using var brush = new SolidBrush(color);

        switch (step.State)
        {
            case LauncherStepState.Done:
                g.DrawEllipse(pen, markerRect);
                g.DrawLine(pen, markerRect.Left + 3, markerRect.Top + 6, markerRect.Left + 5, markerRect.Top + 8);
                g.DrawLine(pen, markerRect.Left + 5, markerRect.Top + 8, markerRect.Left + 9, markerRect.Top + 3);
                break;
            case LauncherStepState.Current:
                g.FillEllipse(brush, markerRect);
                break;
            case LauncherStepState.Failed:
                g.DrawEllipse(pen, markerRect);
                g.DrawLine(pen, markerRect.Left + 3, markerRect.Top + 3, markerRect.Right - 3, markerRect.Bottom - 3);
                g.DrawLine(pen, markerRect.Right - 3, markerRect.Top + 3, markerRect.Left + 3, markerRect.Bottom - 3);
                break;
            default:
                g.DrawEllipse(pen, markerRect);
                break;
        }

        using var textBrush = new SolidBrush(step.State == LauncherStepState.Failed ? Color.FromArgb(185, 28, 28) : step.State == LauncherStepState.Pending ? TextHint : TextMuted);
        using var font = new Font("Segoe UI", 8.2f, step.State == LauncherStepState.Current ? FontStyle.Bold : FontStyle.Regular);
        g.DrawString(step.Label, font, textBrush, x + 18, y - 1);
    }

    private void DrawWindowFrame(Graphics g, Rectangle rect, int radius, float opacity, bool front)
    {
        using var path = SplashRoundedRect(rect, radius);
        using var whiteBrush = new SolidBrush(Color.FromArgb((int)(255 * opacity), 255, 255, 255));
        using var borderPen = new Pen(Color.FromArgb((int)(255 * opacity), front ? WindowBorder : WindowSoftBorder), 1f);

        g.FillPath(whiteBrush, path);
        g.DrawPath(borderPen, path);

        if (!front)
        {
            return;
        }

        // .win-head:before left/right/top 4, height 23, radius 9.
        var bar = new Rectangle(rect.X + 4, rect.Y + 4, rect.Width - 8, 23);
        using (var barBrush = new SolidBrush(BrandBlue))
        {
            FillRounded(g, barBrush, bar, 9);
        }

        for (var i = 0; i < 3; i++)
        {
            FillCircle(g, Color.FromArgb(230, 255, 255, 255), rect.X + 13 + (i * 11), rect.Y + 13, 5);
        }

        // Body inner: centered like HTML .inner left 50%, top 45%.
        var bodyTop = rect.Y + 38;
        var bodyHeight = 86;
        var centerX = rect.X + rect.Width / 2;
        var innerCenterY = bodyTop + (int)(bodyHeight * 0.45f);

        var logoTop = innerCenterY - 28;
        DrawLogo(g, centerX - 11, logoTop, 22);

        using var blueBrush = new SolidBrush(BrandBlue);
        using var softBrush = new SolidBrush(Color.FromArgb(88, BrandBlue));
        using var midBrush = new SolidBrush(Color.FromArgb(132, BrandBlue));

        var line1Y = logoTop + 33;
        FillRounded(g, blueBrush, new Rectangle(centerX - 39, line1Y, 78, 4), 2);
        FillRounded(g, softBrush, new Rectangle(centerX - 46, line1Y + 9, 92, 3), 2);
        FillRounded(g, midBrush, new Rectangle(centerX - 24, line1Y + 19, 48, 2), 2);
    }

    private void DrawBottom(Graphics g)
    {
        // .bottom padding 14px 40px 30px 40px.
        var bottomX = 40;
        DrawText(g, ProgressText, smallBoldFont, string.IsNullOrWhiteSpace(ErrorText) ? TextMuted : Color.FromArgb(185, 28, 28), bottomX, 331);
        DrawTextRight(g, IsIndeterminate ? "Working..." : $"{Math.Clamp(ProgressPercent, 0, 100)}%", smallBoldFont, BrandBlue, 680, 331);

        if (!string.IsNullOrWhiteSpace(ErrorText))
        {
            DrawText(g, ErrorText!, footerFont, Color.FromArgb(185, 28, 28), bottomX, 345);
        }

        var trackRect = new Rectangle(bottomX, 360, 640, 3);
        using (var trackBrush = new SolidBrush(Track))
        {
            FillRounded(g, trackBrush, trackRect, 2);
        }

        using (var fillBrush = new SolidBrush(BrandBlue))
        {
            if (IsIndeterminate)
            {
                FillRounded(g, fillBrush, new Rectangle(trackRect.X, trackRect.Y, 110, trackRect.Height), 2);
            }
            else
            {
                var fillWidth = (int)(trackRect.Width * Math.Clamp(ProgressPercent, 0, 100) / 100f);
                if (fillWidth > 0)
                {
                    FillRounded(g, fillBrush, new Rectangle(trackRect.X, trackRect.Y, fillWidth, trackRect.Height), 2);
                }
            }
        }

        DrawText(g, "A Product of SparkPair", footerFont, TextHint, bottomX, 381);
        DrawTextRight(g, InstalledVersionText, footerFont, TextHint, 680, 381);
    }

    private void DrawMainGrid(Graphics g, Rectangle main)
    {
        using var gridPen = new Pen(GridLine, 1f);
        for (var x = main.Left; x <= main.Right; x += 58)
        {
            g.DrawLine(gridPen, x, main.Top, x, main.Bottom);
        }

        for (var y = main.Top; y <= main.Bottom; y += 58)
        {
            g.DrawLine(gridPen, main.Left, y, main.Right, y);
        }
    }

    private void DrawPill(Graphics g, Rectangle rect, string text)
    {
        using (var brush = new SolidBrush(Color.White))
        using (var pen = new Pen(WindowSoftBorder, 1f))
        using (var path = SplashRoundedRect(rect, 10))
        {
            g.FillPath(brush, path);
            g.DrawPath(pen, path);
        }

        using var sf = new StringFormat { Alignment = StringAlignment.Center, LineAlignment = StringAlignment.Center };
        using var textBrush = new SolidBrush(Color.FromArgb(30, 42, 61));
        g.DrawString(text, pillFont, textBrush, rect, sf);
    }

    private static int PillWidth(string text)
    {
        return text.Length switch
        {
            >= 12 => 112,
            >= 10 => 100,
            >= 8 => 88,
            _ => 72,
        };
    }

    private void DrawLogo(Graphics g, int x, int y, int size)
    {
        var logo = GetEmbeddedLogo();
        if (logo is not null)
        {
            g.DrawImage(logo, new Rectangle(x, y, size, size));
            return;
        }

        // Fallback only if embedded PNG is unavailable.
        DrawBrandMark(g, x, y, size);
    }

    private Image? GetEmbeddedLogo()
    {
        if (embeddedLogo is not null)
        {
            return embeddedLogo;
        }

        if (string.IsNullOrWhiteSpace(EmbeddedLogoPngBase64))
        {
            return null;
        }

        try
        {
            var bytes = Convert.FromBase64String(EmbeddedLogoPngBase64);
            var stream = new MemoryStream(bytes);
            embeddedLogo = Image.FromStream(stream);
            return embeddedLogo;
        }
        catch
        {
            return null;
        }
    }

    private void DrawBrandMark(Graphics g, int x, int y, int size)
    {
        using var brush = new SolidBrush(BrandBlue);

        var r = new Rectangle(x, y, size, size);
        var leftBlob = new Rectangle(r.X, r.Y + 1, (int)(size * 0.66), size - 2);
        var topBlob = new Rectangle(r.X + (int)(size * 0.56), r.Y, (int)(size * 0.38), (int)(size * 0.38));
        var bottomBlob = new Rectangle(r.X + (int)(size * 0.52), r.Y + (int)(size * 0.52), (int)(size * 0.45), (int)(size * 0.42));

        using (var leftPath = new GraphicsPath())
        {
            leftPath.AddPie(leftBlob, 90, 270);
            g.FillPath(brush, leftPath);
        }

        g.FillEllipse(brush, topBlob);
        FillRounded(g, brush, bottomBlob, Math.Max(4, size / 5));
    }

    private static void DrawText(Graphics g, string text, Font font, Color color, int x, int y)
    {
        using var brush = new SolidBrush(color);
        g.DrawString(text, font, brush, x, y);
    }

    private static void DrawTextRight(Graphics g, string text, Font font, Color color, int right, int y)
    {
        using var brush = new SolidBrush(color);
        using var format = new StringFormat { Alignment = StringAlignment.Far };
        g.DrawString(text, font, brush, new RectangleF(0, y, right, 24), format);
    }

    private static void FillCircle(Graphics g, Color color, int x, int y, int size)
    {
        using var brush = new SolidBrush(color);
        g.FillEllipse(brush, x, y, size, size);
    }

    private static void FillRounded(Graphics g, Brush brush, Rectangle rect, int radius)
    {
        using var path = SplashRoundedRect(rect, radius);
        g.FillPath(brush, path);
    }

    private static GraphicsPath SplashRoundedRect(Rectangle rect, int radius)
    {
        var path = new GraphicsPath();
        var d = Math.Max(1, radius * 2);
        path.AddArc(rect.Left, rect.Top, d, d, 180, 90);
        path.AddArc(rect.Right - d, rect.Top, d, d, 270, 90);
        path.AddArc(rect.Right - d, rect.Bottom - d, d, d, 0, 90);
        path.AddArc(rect.Left, rect.Bottom - d, d, d, 90, 90);
        path.CloseFigure();
        return path;
    }

    protected override void Dispose(bool disposing)
    {
        if (disposing)
        {
            embeddedLogo?.Dispose();
            brandFont.Dispose();
            secureFont.Dispose();
            titleFont.Dispose();
            bodyFont.Dispose();
            pillFont.Dispose();
            smallBoldFont.Dispose();
            footerFont.Dispose();
        }

        base.Dispose(disposing);
    }
}

internal sealed class RoundedPanel : Panel
{
    public int BorderRadius { get; set; } = 18;
    public Color BorderColor { get; set; } = Color.FromArgb(199, 208, 220);

    public RoundedPanel()
    {
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw | ControlStyles.UserPaint, true);
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e);
        e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
        using var path = RoundedRect(new Rectangle(0, 0, Width - 1, Height - 1), BorderRadius);
        using var pen = new Pen(BorderColor, 1f);
        e.Graphics.DrawPath(pen, path);
    }

    protected override void OnResize(EventArgs eventargs)
    {
        base.OnResize(eventargs);
        if (Width <= 0 || Height <= 0)
        {
            return;
        }

        using var path = RoundedRect(new Rectangle(0, 0, Width, Height), BorderRadius);
        Region = new Region(path);
    }

    private static GraphicsPath RoundedRect(Rectangle r, int radius)
    {
        var path = new GraphicsPath();
        var d = Math.Max(1, radius * 2);
        path.AddArc(r.Left, r.Top, d, d, 180, 90);
        path.AddArc(r.Right - d, r.Top, d, d, 270, 90);
        path.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
        path.AddArc(r.Left, r.Bottom - d, d, d, 90, 90);
        path.CloseFigure();
        return path;
    }
}

internal sealed class GridPanel : Panel
{
    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e);
        using var pen = new Pen(Color.FromArgb(5, 64, 87, 232));
        for (var x = 0; x < Width; x += 56)
        {
            e.Graphics.DrawLine(pen, x, 0, x, Height);
        }
        for (var y = 0; y < Height; y += 56)
        {
            e.Graphics.DrawLine(pen, 0, y, Width, y);
        }
    }
}

internal sealed class StatusDot : Control
{
    public Color DotColor { get; set; } = Color.FromArgb(64, 87, 232);

    public StatusDot()
    {
        Width = 7;
        Height = 7;
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.UserPaint, true);
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
        using var brush = new SolidBrush(DotColor);
        e.Graphics.FillEllipse(brush, 0, 0, Width - 1, Height - 1);
    }
}

internal sealed class VerticalLabel : Control
{
    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
        using var brush = new SolidBrush(ForeColor);
        using var format = new StringFormat { Alignment = StringAlignment.Center, LineAlignment = StringAlignment.Center };
        e.Graphics.TranslateTransform(Width / 2f, Height / 2f);
        e.Graphics.RotateTransform(-90);
        e.Graphics.DrawString(Text, Font, brush, new RectangleF(-Height / 2f, -Width / 2f, Height, Width), format);
        e.Graphics.ResetTransform();
    }
}

internal sealed class PillLabel : Label
{
    public PillLabel()
    {
        AutoSize = false;
        TextAlign = ContentAlignment.MiddleCenter;
        Font = new Font("Segoe UI", 8.2f, FontStyle.Bold);
        ForeColor = Color.FromArgb(64, 82, 82);
        BackColor = Color.White;
        Margin = new Padding(0, 0, 8, 0);
        Padding = new Padding(8, 0, 8, 0);
        SetStyle(ControlStyles.UserPaint, true);
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
        var rect = new Rectangle(0, 0, Width - 1, Height - 1);
        using var path = RoundedRect(rect, Height / 2);
        using var bg = new SolidBrush(BackColor);
        using var pen = new Pen(Color.FromArgb(199, 208, 220));
        e.Graphics.FillPath(bg, path);
        e.Graphics.DrawPath(pen, path);
        TextRenderer.DrawText(e.Graphics, Text, Font, rect, ForeColor, TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);
    }

    private static GraphicsPath RoundedRect(Rectangle r, int radius)
    {
        var path = new GraphicsPath();
        var d = Math.Max(1, radius * 2);
        path.AddArc(r.Left, r.Top, d, d, 180, 90);
        path.AddArc(r.Right - d, r.Top, d, d, 270, 90);
        path.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
        path.AddArc(r.Left, r.Bottom - d, d, d, 90, 90);
        path.CloseFigure();
        return path;
    }
}

internal sealed class RichTextLabel : Control
{
    public string NormalText { get; set; } = "";
    public string AccentText { get; set; } = "";
    public float FontSize { get; set; } = 33f;

    protected override void OnPaint(PaintEventArgs e)
    {
        e.Graphics.TextRenderingHint = System.Drawing.Text.TextRenderingHint.ClearTypeGridFit;
        using var normalFont = new Font("Segoe UI", FontSize, FontStyle.Regular);
        using var accentFont = new Font("Segoe UI", FontSize, FontStyle.Regular);
        using var normal = new SolidBrush(Color.FromArgb(15, 23, 42));
        using var accent = new SolidBrush(Color.FromArgb(64, 87, 232));
        e.Graphics.DrawString(NormalText, normalFont, normal, 0, 0);
        e.Graphics.DrawString(AccentText, accentFont, accent, 0, FontSize + 5);
    }
}

internal sealed class WindowStackVisual : Control
{
    public Bitmap? Logo { get; set; }

    public WindowStackVisual()
    {
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw | ControlStyles.UserPaint, true);
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e);
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;

        var card = new Rectangle(6, 8, 205, 135);
        using (var shadow = new SolidBrush(Color.FromArgb(12, 64, 87, 232)))
        {
            g.FillRoundedRectangle(shadow, new Rectangle(card.X + 8, card.Y + 10, card.Width, card.Height), 22);
        }
        DrawRounded(g, card, Color.White, Color.FromArgb(174, 185, 202), 22);

        using (var pen = new Pen(Color.FromArgb(215, 221, 231)))
        {
            g.DrawLine(pen, card.Left, card.Top + 38, card.Right, card.Top + 38);
        }

        using (var dot = new SolidBrush(Color.FromArgb(195, 202, 212)))
        using (var blue = new SolidBrush(Color.FromArgb(64, 87, 232)))
        {
            g.FillEllipse(dot, card.Left + 14, card.Top + 16, 6, 6);
            g.FillEllipse(dot, card.Left + 27, card.Top + 16, 6, 6);
            g.FillEllipse(blue, card.Left + 40, card.Top + 16, 6, 6);
        }
        using (var blueBrush = new SolidBrush(Color.FromArgb(64, 87, 232)))
        using (var font = new Font("Segoe UI", 7.8f, FontStyle.Bold))
        {
            g.DrawString("v1.8.25", font, blueBrush, card.Right - 54, card.Top + 12);
        }

        var fileRow = new Rectangle(card.Left + 14, card.Top + 52, 177, 50);
        DrawRounded(g, fileRow, Color.FromArgb(248, 250, 252), Color.FromArgb(211, 218, 229), 15);
        var logoRect = new Rectangle(fileRow.Left + 11, fileRow.Top + 8, 34, 34);
        DrawRounded(g, logoRect, Color.White, Color.FromArgb(211, 218, 229), 11);
        if (Logo is not null)
        {
            g.DrawImage(Logo, new Rectangle(logoRect.Left + 4, logoRect.Top + 4, 26, 26));
        }
        else
        {
            using var f = new Font("Segoe UI", 10f, FontStyle.Bold);
            using var b = new SolidBrush(Color.FromArgb(64, 87, 232));
            g.DrawString("G", f, b, logoRect.Left + 9, logoRect.Top + 7);
        }

        using (var blueLine = new SolidBrush(Color.FromArgb(56, 64, 87, 232)))
        using (var line = new SolidBrush(Color.FromArgb(219, 225, 234)))
        {
            g.FillRoundedRectangle(blueLine, new Rectangle(fileRow.Left + 56, fileRow.Top + 15, 64, 4), 2);
            g.FillRoundedRectangle(line, new Rectangle(fileRow.Left + 56, fileRow.Top + 27, 42, 4), 2);
        }

        using (var tiny = new Font("Segoe UI", 7.4f, FontStyle.Bold))
        using (var muted = new SolidBrush(Color.FromArgb(83, 97, 113)))
        using (var blueText = new SolidBrush(Color.FromArgb(64, 87, 232)))
        {
            g.DrawString("FILE", tiny, muted, card.Left + 14, card.Top + 112);
            g.DrawString("Ready", tiny, blueText, card.Right - 61, card.Top + 112);
        }
    }

    private static void DrawRounded(Graphics g, Rectangle rect, Color fill, Color border, int radius)
    {
        using var path = RoundedRect(rect, radius);
        using var brush = new SolidBrush(fill);
        using var pen = new Pen(border);
        g.FillPath(brush, path);
        g.DrawPath(pen, path);
    }

    private static GraphicsPath RoundedRect(Rectangle r, int radius)
    {
        var path = new GraphicsPath();
        var d = Math.Max(1, radius * 2);
        path.AddArc(r.Left, r.Top, d, d, 180, 90);
        path.AddArc(r.Right - d, r.Top, d, d, 270, 90);
        path.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
        path.AddArc(r.Left, r.Bottom - d, d, d, 90, 90);
        path.CloseFigure();
        return path;
    }
}

internal static class GraphicsExtensions
{
    public static void FillRoundedRectangle(this Graphics graphics, Brush brush, Rectangle bounds, int radius)
    {
        using var path = RoundedRect(bounds, radius);
        graphics.FillPath(brush, path);
    }

    private static GraphicsPath RoundedRect(Rectangle r, int radius)
    {
        var path = new GraphicsPath();
        var d = Math.Max(1, radius * 2);
        path.AddArc(r.Left, r.Top, d, d, 180, 90);
        path.AddArc(r.Right - d, r.Top, d, d, 270, 90);
        path.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
        path.AddArc(r.Left, r.Bottom - d, d, d, 90, 90);
        path.CloseFigure();
        return path;
    }
}
