import SwiftUI

struct LoginView: View {
    @EnvironmentObject private var session: SessionStore
    @Environment(\.openURL) private var openURL

    @State private var email = ""
    @State private var password = ""
    @State private var showSignUp = false
    @State private var keepSignedIn = true

    @FocusState private var focusedField: Field?
    enum Field {
        case email
        case password
    }

    var body: some View {
        NavigationStack {
            ZStack {
                BBColors.backgroundGradient
                    .ignoresSafeArea()

                ScrollView(showsIndicators: false) {
                    VStack {
                        Spacer(minLength: 24)

                        // Split-panel auth card — mirrors css/login.css .container.
                        // On mobile the branded image is a banner on top (order:-1).
                        VStack(spacing: 0) {
                            AuthHeroBanner(
                                title: "Track. Earn XP. Level up.",
                                subtitle: "Your wellness journey, gamified."
                            )
                            formSection
                        }
                        .authCard()

                        Spacer(minLength: 24)
                    }
                    .padding(.horizontal, 16)
                    .frame(maxWidth: .infinity)
                }
            }
            .sheet(isPresented: $showSignUp) {
                SignUpView()
            }
        }
    }

    // MARK: - Form (css/login.css .form-section)

    private var formSection: some View {
        VStack(alignment: .leading, spacing: 16) {
            VStack(alignment: .leading, spacing: 6) {
                Text("Welcome back")
                    .font(BBFont.displayBold)
                    .foregroundColor(BBColors.text)
                Text("Sign in to keep your streak alive.")
                    .font(BBFont.font(BBFont.base, .medium))
                    .foregroundColor(BBColors.textSecondary)
            }
            .padding(.bottom, 6)

            TextField("Email", text: $email)
                .textContentType(.emailAddress)
                .keyboardType(.emailAddress)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .focused($focusedField, equals: .email)
                .bbInput(isFocused: focusedField == .email)

            SecureField("Password", text: $password)
                .textContentType(.password)
                .focused($focusedField, equals: .password)
                .bbInput(isFocused: focusedField == .password)

            Toggle(isOn: $keepSignedIn) {
                Text("Keep signed in")
                    .font(BBFont.font(BBFont.sm, .bold))
                    .foregroundColor(BBColors.textSecondary)
            }
            .tint(BBColors.primary)
            .padding(.vertical, 2)

            if let errorMessage = session.errorMessage {
                HStack(spacing: 10) {
                    Image(systemName: "exclamationmark.triangle.fill")
                        .font(BBFont.bodyBold)
                    Text(errorMessage)
                        .font(BBFont.font(BBFont.sm, .bold))
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                .bbAlert(isSuccess: false)
                .transition(.opacity.combined(with: .scale))
            }

            Button {
                focusedField = nil
                Task {
                    await session.signIn(email: email, password: password, remember: keepSignedIn)
                }
            } label: {
                if session.isLoading {
                    ProgressView()
                        .tint(.white)
                        .frame(maxWidth: .infinity)
                } else {
                    Text("Sign In")
                        .frame(maxWidth: .infinity)
                }
            }
            .buttonStyle(BBButtonStyle(
                backgroundColor: BBColors.primary,
                shadowColor: BBColors.primaryHover,
                isEnabled: !email.isEmpty && !password.isEmpty && !session.isLoading
            ))
            .disabled(session.isLoading || email.isEmpty || password.isEmpty)
            .padding(.top, 2)

            Button {
                if let url = URL(string: "https://titan.csit.rmit.edu.au/~s3974781/bitbalance/reset_password.php") {
                    openURL(url)
                }
            } label: {
                Text("Forgot password?")
                    .font(BBFont.font(BBFont.sm, .bold))
                    .foregroundColor(BBColors.primary)
                    .frame(maxWidth: .infinity)
            }
            .padding(.top, 2)

            HStack(spacing: 4) {
                Text("Don't have an account?")
                    .font(BBFont.font(BBFont.sm, .medium))
                    .foregroundColor(BBColors.textSecondary)
                Button {
                    session.errorMessage = nil
                    showSignUp = true
                } label: {
                    Text("Sign Up")
                        .font(BBFont.font(BBFont.sm, .heavy))
                        .foregroundColor(BBColors.primary)
                }
            }
            .frame(maxWidth: .infinity)
            .padding(.top, 4)
        }
        .padding(.horizontal, 28)
        .padding(.vertical, 32)
    }
}

// MARK: - Shared auth chrome (used by LoginView & SignUpView)

/// Branded food-photo banner with brand tint + bottom scrim + tagline.
/// Mirrors css/login.css .side-section (which becomes a top banner on mobile).
struct AuthHeroBanner: View {
    let title: String
    let subtitle: String

    var body: some View {
        ZStack(alignment: .bottomLeading) {
            Image("food")
                .resizable()
                .aspectRatio(contentMode: .fill)
                .frame(maxWidth: .infinity)
                .frame(height: 190)

            // Brand tint (green → blue), matches .side-section::before
            LinearGradient(
                gradient: Gradient(colors: [
                    Color(hex: "58CC02").opacity(0.30),
                    Color(hex: "1CB0F6").opacity(0.28)
                ]),
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )

            // Dark scrim rising from the bottom, matches .side-section::after
            LinearGradient(
                gradient: Gradient(stops: [
                    .init(color: Color.black.opacity(0.80), location: 0.0),
                    .init(color: Color.black.opacity(0.35), location: 0.30),
                    .init(color: Color.clear, location: 0.58)
                ]),
                startPoint: .bottom,
                endPoint: .top
            )

            VStack(alignment: .leading, spacing: 6) {
                Text(title)
                    .font(BBFont.font(22, .heavy))
                    .foregroundColor(.white)
                Text(subtitle)
                    .font(BBFont.font(15, .medium))
                    .foregroundColor(.white.opacity(0.92))
            }
            .shadow(color: Color.black.opacity(0.45), radius: 6, x: 0, y: 1)
            .padding(.horizontal, 24)
            .padding(.bottom, 22)
        }
        .frame(height: 190)
        .frame(maxWidth: .infinity)
        .clipped()
    }
}

/// Card chrome shared by the auth screens: surface fill, 2px border,
/// a 10px tactile block underneath (box-shadow: 0 10px 0 border-subtle) + soft md shadow.
struct AuthCardModifier: ViewModifier {
    func body(content: Content) -> some View {
        content
            .background(BBColors.surface)
            .clipShape(RoundedRectangle(cornerRadius: BBRadius.lg))
            .overlay(
                RoundedRectangle(cornerRadius: BBRadius.lg)
                    .stroke(BBColors.border, lineWidth: 2)
            )
            .background(
                RoundedRectangle(cornerRadius: BBRadius.lg)
                    .fill(BBColors.borderSubtle)
                    .offset(y: 10)
            )
            .bbShadow(.md)
            .frame(maxWidth: 480)
    }
}

extension View {
    func authCard() -> some View {
        modifier(AuthCardModifier())
    }
}

// MARK: - Previews
#Preview("Mặc định") {
    LoginView()
        .environmentObject(SessionStore.preview)
}

#Preview("Có lỗi đăng nhập") {
    LoginView()
        .environmentObject(SessionStore.previewWithError("Email hoặc mật khẩu không chính xác!"))
}

#Preview("Dark mode") {
    LoginView()
        .environmentObject(SessionStore.preview)
        .preferredColorScheme(.dark)
}
