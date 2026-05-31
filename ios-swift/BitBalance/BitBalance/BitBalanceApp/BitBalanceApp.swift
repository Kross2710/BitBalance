import SwiftUI
import UIKit
import AVFoundation
import AudioToolbox

@main
struct BitBalanceApp: App {
    @StateObject private var session = SessionStore(api: APIClient(baseURL: AppConfig.baseURL))

    var body: some Scene {
        WindowGroup {
            RootView()
                .environmentObject(session)
        }
    }
}

// MARK: - ==========================================
// MARK: DESIGN SYSTEM (Theme.swift contents compiled globally)
// MARK: - ==========================================

// MARK: - Color Hex Helper
extension Color {
    init(hex: String) {
        let hex = hex.trimmingCharacters(in: CharacterSet.alphanumerics.inverted)
        var int: UInt64 = 0
        Scanner(string: hex).scanHexInt64(&int)
        let a, r, g, b: UInt64
        switch hex.count {
        case 3: // RGB (12-bit)
            (a, r, g, b) = (255, (int >> 8) * 17, (int >> 4 & 0xF) * 17, (int & 0xF) * 17)
        case 6: // RGB (24-bit)
            (a, r, g, b) = (255, int >> 16, int >> 8 & 0xFF, int & 0xFF)
        case 8: // ARGB (32-bit)
            (a, r, g, b) = (int >> 24, int >> 16 & 0xFF, int >> 8 & 0xFF, int & 0xFF)
        default:
            (a, r, g, b) = (255, 0, 0, 0)
        }
        self.init(
            .sRGB,
            red: Double(r) / 255,
            green: Double(g) / 255,
            blue: Double(b) / 255,
            opacity: Double(a) / 255
        )
    }
}

extension UIColor {
    convenience init(hex: String) {
        let hex = hex.trimmingCharacters(in: CharacterSet.alphanumerics.inverted)
        var int: UInt64 = 0
        Scanner(string: hex).scanHexInt64(&int)
        let a, r, g, b: UInt64
        switch hex.count {
        case 3: // RGB (12-bit)
            (a, r, g, b) = (255, (int >> 8) * 17, (int >> 4 & 0xF) * 17, (int & 0xF) * 17)
        case 6: // RGB (24-bit)
            (a, r, g, b) = (255, int >> 16, int >> 8 & 0xFF, int & 0xFF)
        case 8: // ARGB (32-bit)
            (a, r, g, b) = (int >> 24, int >> 16 & 0xFF, int >> 8 & 0xFF, int & 0xFF)
        default:
            (a, r, g, b) = (255, 0, 0, 0)
        }
        self.init(
            red: CGFloat(r) / 255.0,
            green: CGFloat(g) / 255.0,
            blue: CGFloat(b) / 255.0,
            alpha: CGFloat(a) / 255.0
        )
    }
}

// MARK: - Brand Design System Tokens
struct BBColors {
    static func dynamicColor(light: String, dark: String) -> Color {
        Color(UIColor { traitCollection in
            if traitCollection.userInterfaceStyle == .dark {
                return UIColor(hex: dark)
            } else {
                return UIColor(hex: light)
            }
        })
    }
    
    // Core brand & accents
    static let primary = dynamicColor(light: "58CC02", dark: "4ADE80")      // Brand Green
    static let primaryHover = dynamicColor(light: "4CAF00", dark: "22C55E") // 3D Button shadow
    static let secondary = dynamicColor(light: "1CB0F6", dark: "60A5FA")    // Brand Blue
    static let accent = dynamicColor(light: "FF9600", dark: "FB923C")       // Brand Orange (Streak/Level)
    
    // Adaptive surfaces & backgrounds
    static let background = dynamicColor(light: "F8FAFC", dark: "0F172A")
    static let surface = dynamicColor(light: "FFFFFF", dark: "1E2937")
    static let surfaceAlt = dynamicColor(light: "F1F5F9", dark: "334155")
    
    // Adaptive text
    static let text = dynamicColor(light: "1E2937", dark: "F1F5F9")
    static let textSecondary = dynamicColor(light: "64748B", dark: "94A3B8")
    static let textMuted = dynamicColor(light: "94A3B8", dark: "64748B")
    
    // Borders
    static let border = dynamicColor(light: "E2E8F0", dark: "475569")
    static let borderSubtle = dynamicColor(light: "F1F5F9", dark: "334155")
    
    // Danger / Status
    static let danger = dynamicColor(light: "EF4444", dark: "F87171")
    static let dangerBg = dynamicColor(light: "FEE2E2", dark: "7F1D1D")
    static let dangerBorder = dynamicColor(light: "FCA5A5", dark: "F87171")
    
    static let success = dynamicColor(light: "58CC02", dark: "4ADE80")
    static let successBg = dynamicColor(light: "E8F5E9", dark: "14532D")
    static let successBorder = dynamicColor(light: "81C784", dark: "4ADE80")
    
    static let warning = dynamicColor(light: "F59E0B", dark: "FBBF24")
    static let warningBg = dynamicColor(light: "FEF3C7", dark: "78350F")

    static let info = dynamicColor(light: "3B82F6", dark: "60A5FA")
    static let infoBg = dynamicColor(light: "DBEAFE", dark: "1E3A8A")

    // Extra tokens mirrored from css/tokens.css
    static let surfaceHover = dynamicColor(light: "E0F2FE", dark: "475569")
    static let accentHover = dynamicColor(light: "E67E00", dark: "EA580C")
    static let primarySoft = primary.opacity(0.12)          // --color-primary-soft
    // Modal/sheet scrim — ARGB hex (0x80 ≈ 0.5 alpha, 0xB3 ≈ 0.7 alpha)
    static let overlay = dynamicColor(light: "80000000", dark: "B3000000")

    // Common Gradients
    static let primaryGradient = LinearGradient(
        gradient: Gradient(colors: [primary, primaryHover]),
        startPoint: .topLeading,
        endPoint: .bottomTrailing
    )
    
    static let streakGradient = LinearGradient(
        gradient: Gradient(colors: [Color(hex: "FF6B00"), Color(hex: "FF9600")]),
        startPoint: .topLeading,
        endPoint: .bottomTrailing
    )
    
    static let backgroundGradient = LinearGradient(
        gradient: Gradient(colors: [primary.opacity(0.1), background]),
        startPoint: .top,
        endPoint: .bottom
    )
}

struct BBRadius {
    static let sm: CGFloat = 8
    static let md: CGFloat = 14
    static let lg: CGFloat = 20
    static let xl: CGFloat = 28
    static let pill: CGFloat = 9999
}

// MARK: - Typography
// Scale mirrors css/tokens.css --font-size-* (1rem = 16pt). Uses the system
// font (.default) to match the web's -apple-system stack.
struct BBFont {
    // Size scale
    static let xs: CGFloat = 12      // 0.75rem
    static let sm: CGFloat = 14      // 0.875rem
    static let base: CGFloat = 16    // 1rem
    static let lg: CGFloat = 18      // 1.125rem
    static let xl: CGFloat = 20      // 1.25rem
    static let title: CGFloat = 24
    static let display: CGFloat = 32

    static func font(_ size: CGFloat, _ weight: Font.Weight = .regular) -> Font {
        .system(size: size, weight: weight)
    }

    // Ready-made styles
    static let caption = font(xs)
    static let captionBold = font(xs, .semibold)
    static let small = font(sm)
    static let body = font(base)
    static let bodyBold = font(base, .bold)
    static let subheading = font(lg, .semibold)
    static let heading = font(xl, .bold)
    static let titleBold = font(title, .heavy)
    static let displayBold = font(display, .heavy)
}

// MARK: - Shadow scale (mirrors css/tokens.css --shadow-*)
// CSS blur radius is roughly halved for SwiftUI's blur-sigma radius.
enum BBShadow {
    case xs, sm, md, lg, xl

    var color: Color {
        switch self {
        case .xs: return Color.black.opacity(0.05)
        case .sm: return Color.black.opacity(0.06)
        case .md: return Color.black.opacity(0.08)
        case .lg: return Color(hex: "0F172A").opacity(0.08)
        case .xl: return Color(hex: "0F172A").opacity(0.12)
        }
    }

    var radius: CGFloat {
        switch self {
        case .xs: return 1
        case .sm: return 4
        case .md: return 8
        case .lg: return 15
        case .xl: return 25
        }
    }

    var y: CGFloat {
        switch self {
        case .xs: return 1
        case .sm: return 2
        case .md: return 4
        case .lg: return 10
        case .xl: return 20
        }
    }
}

// MARK: - Reusable View Modifiers

/// 3D Tactile Card Style
struct BBCardModifier: ViewModifier {
    var radius: CGFloat = BBRadius.lg
    var padding: CGFloat = 16
    
    func body(content: Content) -> some View {
        content
            .padding(padding)
            .background(BBColors.surface)
            .cornerRadius(radius)
            .overlay(
                RoundedRectangle(cornerRadius: radius)
                    .stroke(BBColors.border, lineWidth: 2)
                    .allowsHitTesting(false)
            )
            // 3D tactile offset layer using borderSubtle
            .background(
                RoundedRectangle(cornerRadius: radius)
                    .fill(BBColors.borderSubtle)
                    .offset(y: 8)
            )
            // Soft drop shadow
            .shadow(color: Color.black.opacity(0.06), radius: 8, x: 0, y: 2)
            .padding(.bottom, 8)
    }
}

/// 3D Tactile Button Style
struct BBButtonStyle: ButtonStyle {
    var backgroundColor: Color = BBColors.primary
    var shadowColor: Color = BBColors.primaryHover
    var textColor: Color = .white
    var radius: CGFloat = BBRadius.md
    var isEnabled: Bool = true
    
    func makeBody(configuration: Configuration) -> some View {
        let activeBg = isEnabled ? backgroundColor : BBColors.surfaceAlt
        let activeShadow = isEnabled ? shadowColor : BBColors.border
        let activeText = isEnabled ? textColor : BBColors.textSecondary
        
        configuration.label
            .font(.system(size: 16, weight: .bold))
            .foregroundColor(activeText)
            .padding(.vertical, 14)
            .padding(.horizontal, 28)
            .frame(maxWidth: .infinity)
            .background(activeBg)
            .cornerRadius(radius)
            // The 3D tactile block shadow (4pt thick)
            .background(
                RoundedRectangle(cornerRadius: radius)
                    .fill(activeShadow)
                    .offset(y: configuration.isPressed ? 0 : 4)
            )
            // Push/compress the button down by 4pt when pressed
            .offset(y: configuration.isPressed ? 4 : 0)
            .animation(.interactiveSpring(response: 0.12, dampingFraction: 0.85, blendDuration: 0), value: configuration.isPressed)
    }
}

/// Bordered Form Input Modifier with Focus Glow
struct BBInputModifier: ViewModifier {
    var isFocused: Bool = false
    var radius: CGFloat = BBRadius.md
    
    func body(content: Content) -> some View {
        content
            .padding(.vertical, 12)
            .padding(.horizontal, 16)
            .background(isFocused ? BBColors.surface : BBColors.surfaceAlt)
            .foregroundColor(BBColors.text)
            .cornerRadius(radius)
            .overlay(
                RoundedRectangle(cornerRadius: radius)
                    .stroke(isFocused ? BBColors.primary : BBColors.border, lineWidth: 2)
                    .allowsHitTesting(false)
            )
            .shadow(color: isFocused ? BBColors.primary.opacity(0.12) : Color.clear, radius: 4, x: 0, y: 0)
    }
}

/// 3D Tactile Alert/Status Banner
struct BBAlertModifier: ViewModifier {
    var isSuccess: Bool
    var radius: CGFloat = BBRadius.md
    
    func body(content: Content) -> some View {
        content
            .padding(16)
            .background(isSuccess ? BBColors.successBg : BBColors.dangerBg)
            .foregroundColor(isSuccess ? BBColors.success : BBColors.danger)
            .cornerRadius(radius)
            .overlay(
                RoundedRectangle(cornerRadius: radius)
                    .stroke(isSuccess ? BBColors.successBorder : BBColors.dangerBorder, lineWidth: 2)
                    .allowsHitTesting(false)
            )
            .background(
                RoundedRectangle(cornerRadius: radius)
                    .fill(isSuccess ? BBColors.successBorder : BBColors.dangerBorder)
                    .offset(y: 4)
            )
            .padding(.bottom, 4)
    }
}

// MARK: - View Extension Shortcuts
extension View {
    func bbCard(radius: CGFloat = BBRadius.lg, padding: CGFloat = 16) -> some View {
        modifier(BBCardModifier(radius: radius, padding: padding))
    }
    
    func bbInput(isFocused: Bool = false, radius: CGFloat = BBRadius.md) -> some View {
        modifier(BBInputModifier(isFocused: isFocused, radius: radius))
    }
    
    func bbAlert(isSuccess: Bool, radius: CGFloat = BBRadius.md) -> some View {
        modifier(BBAlertModifier(isSuccess: isSuccess, radius: radius))
    }

    /// Soft drop shadow matching css/tokens.css --shadow-* scale.
    func bbShadow(_ level: BBShadow) -> some View {
        shadow(color: level.color, radius: level.radius, x: 0, y: level.y)
    }
}

// MARK: - Global Barcode Scanner View Representable
struct BarcodeScannerView: UIViewControllerRepresentable {
    let onScan: (String) -> Void
    let onCancel: () -> Void
    
    func makeUIViewController(context: Context) -> BarcodeScannerViewController {
        let controller = BarcodeScannerViewController()
        controller.delegate = context.coordinator
        return controller
    }
    
    func updateUIViewController(_ uiViewController: BarcodeScannerViewController, context: Context) {}
    
    func makeCoordinator() -> Coordinator {
        Coordinator(onScan: onScan, onCancel: onCancel)
    }
    
    class Coordinator: NSObject, BarcodeScannerViewControllerDelegate {
        let onScan: (String) -> Void
        let onCancel: () -> Void
        
        init(onScan: @escaping (String) -> Void, onCancel: @escaping () -> Void) {
            self.onScan = onScan
            self.onCancel = onCancel
        }
        
        func barcodeScannerDidFindCode(_ code: String) {
            onScan(code)
        }
        
        func barcodeScannerDidCancel() {
            onCancel()
        }
    }
}

protocol BarcodeScannerViewControllerDelegate: AnyObject {
    func barcodeScannerDidFindCode(_ code: String)
    func barcodeScannerDidCancel()
}

class BarcodeScannerViewController: UIViewController, AVCaptureMetadataOutputObjectsDelegate {
    weak var delegate: BarcodeScannerViewControllerDelegate?
    private var captureSession: AVCaptureSession?
    private var previewLayer: AVCaptureVideoPreviewLayer?
    
    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .black
        setupSession()
    }
    
    override func viewWillAppear(_ animated: Bool) {
        super.viewWillAppear(animated)
        if let session = captureSession, !session.isRunning {
            DispatchQueue.global(qos: .userInitiated).async {
                session.startRunning()
            }
        }
    }
    
    override func viewWillDisappear(_ animated: Bool) {
        super.viewWillDisappear(animated)
        if let session = captureSession, session.isRunning {
            session.stopRunning()
        }
    }
    
    override func viewDidLayoutSubviews() {
        super.viewDidLayoutSubviews()
        previewLayer?.frame = view.layer.bounds
    }
    
    private func setupSession() {
        let session = AVCaptureSession()
        captureSession = session
        
        guard let videoCaptureDevice = AVCaptureDevice.default(for: .video) else {
            failed()
            return
        }
        
        let videoInput: AVCaptureDeviceInput
        do {
            videoInput = try AVCaptureDeviceInput(device: videoCaptureDevice)
        } catch {
            failed()
            return
        }
        
        if session.canAddInput(videoInput) {
            session.addInput(videoInput)
        } else {
            failed()
            return
        }
        
        let metadataOutput = AVCaptureMetadataOutput()
        if session.canAddOutput(metadataOutput) {
            session.addOutput(metadataOutput)
            metadataOutput.setMetadataObjectsDelegate(self, queue: DispatchQueue.main)
            metadataOutput.metadataObjectTypes = [.ean8, .ean13, .pdf417, .qr, .upce, .code128]
        } else {
            failed()
            return
        }
        
        let preview = AVCaptureVideoPreviewLayer(session: session)
        preview.frame = view.layer.bounds
        preview.videoGravity = .resizeAspectFill
        view.layer.addSublayer(preview)
        previewLayer = preview
        
        addOverlayUI()
    }
    
    private func addOverlayUI() {
        let cancelBtn = UIButton(type: .system)
        cancelBtn.setTitle("Cancel", for: .normal)
        cancelBtn.titleLabel?.font = .systemFont(ofSize: 17, weight: .bold)
        cancelBtn.tintColor = .white
        cancelBtn.addTarget(self, action: #selector(cancelTapped), for: .touchUpInside)
        cancelBtn.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(cancelBtn)
        
        let instruction = UILabel()
        instruction.text = "Align food barcode within frame"
        instruction.font = .systemFont(ofSize: 14, weight: .bold)
        instruction.textColor = .white
        instruction.textAlignment = .center
        instruction.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(instruction)
        
        let viewfinder = UIView()
        viewfinder.layer.borderColor = UIColor(red: 88/255.0, green: 204/255.0, blue: 2/255.0, alpha: 1.0).cgColor
        viewfinder.layer.borderWidth = 3
        viewfinder.layer.cornerRadius = 12
        viewfinder.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(viewfinder)
        
        NSLayoutConstraint.activate([
            cancelBtn.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor, constant: 16),
            cancelBtn.trailingAnchor.constraint(equalTo: view.trailingAnchor, constant: -20),
            
            viewfinder.centerXAnchor.constraint(equalTo: view.centerXAnchor),
            viewfinder.centerYAnchor.constraint(equalTo: view.centerYAnchor),
            viewfinder.widthAnchor.constraint(equalTo: view.widthAnchor, multiplier: 0.8),
            viewfinder.heightAnchor.constraint(equalToConstant: 180),
            
            instruction.bottomAnchor.constraint(equalTo: viewfinder.topAnchor, constant: -16),
            instruction.centerXAnchor.constraint(equalTo: view.centerXAnchor)
        ])
    }
    
    @objc private func cancelTapped() {
        delegate?.barcodeScannerDidCancel()
    }
    
    private func failed() {
        delegate?.barcodeScannerDidCancel()
    }
    
    func metadataOutput(_ output: AVCaptureMetadataOutput, didOutput metadataObjects: [AVMetadataObject], from connection: AVCaptureConnection) {
        if let metadataObject = metadataObjects.first {
            guard let readableObject = metadataObject as? AVMetadataMachineReadableCodeObject else { return }
            guard let stringValue = readableObject.stringValue else { return }
            AudioServicesPlaySystemSound(SystemSoundID(kSystemSoundID_Vibrate))
            delegate?.barcodeScannerDidFindCode(stringValue)
        }
    }
}

// MARK: - Global Image Picker View Representable
struct ImagePicker: UIViewControllerRepresentable {
    var sourceType: UIImagePickerController.SourceType = .photoLibrary
    let onImagePicked: (UIImage) -> Void
    let onCancel: () -> Void
    
    func makeUIViewController(context: Context) -> UIImagePickerController {
        let picker = UIImagePickerController()
        picker.sourceType = sourceType
        picker.delegate = context.coordinator
        return picker
    }
    
    func updateUIViewController(_ uiViewController: UIImagePickerController, context: Context) {}
    
    func makeCoordinator() -> Coordinator {
        Coordinator(onImagePicked: onImagePicked, onCancel: onCancel)
    }
    
    class Coordinator: NSObject, UINavigationControllerDelegate, UIImagePickerControllerDelegate {
        let onImagePicked: (UIImage) -> Void
        let onCancel: () -> Void
        
        init(onImagePicked: @escaping (UIImage) -> Void, onCancel: @escaping () -> Void) {
            self.onImagePicked = onImagePicked
            self.onCancel = onCancel
        }
        
        func imagePickerController(_ picker: UIImagePickerController, didFinishPickingMediaWithInfo info: [UIImagePickerController.InfoKey : Any]) {
            if let image = info[.originalImage] as? UIImage {
                onImagePicked(image)
            } else {
                onCancel()
            }
        }
        
        func imagePickerControllerDidCancel(_ picker: UIImagePickerController) {
            onCancel()
        }
    }
}

