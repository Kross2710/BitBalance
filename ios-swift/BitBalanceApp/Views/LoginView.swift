import SwiftUI

struct LoginView: View {
    @EnvironmentObject private var session: SessionStore
    @Environment(\.openURL) private var openURL
    
    @State private var email = ""
    @State private var password = ""
    @State private var showPassword = false
    
    @FocusState private var focusedField: Field?
    enum Field {
        case email
        case password
    }

    var body: some View {
        NavigationStack {
            ZStack {
                // Vibrant background gradient matching tokens.css
                BBColors.backgroundGradient
                    .ignoresSafeArea()
                
                ScrollView(showsIndicators: false) {
                    VStack(spacing: 24) {
                        Spacer()
                            .frame(height: 30)
                        
                        // Smaller Mascot Logo Badge
                        ZStack {
                            Circle()
                                .fill(BBColors.surface)
                                .frame(width: 80, height: 80)
                                .overlay(
                                    Circle()
                                        .stroke(BBColors.primary, lineWidth: 2)
                                )
                                .background(
                                    Circle()
                                        .fill(BBColors.primaryHover)
                                        .offset(y: 4)
                                )
                                .shadow(color: Color.black.opacity(0.08), radius: 4, x: 0, y: 2)
                            
                            Text("🥗")
                                .font(.system(size: 42))
                        }
                        .padding(.bottom, 8)
                        
                        // 3D tactile card with radius-xl (28px), matching web
                        VStack(spacing: 24) {
                            VStack(spacing: 8) {
                                Text("Welcome back! 👋")
                                    .font(.system(size: 26, weight: .black))
                                    .foregroundColor(BBColors.text)
                                    .multilineTextAlignment(.center)
                                
                                Text("Log in to continue your health journey")
                                    .font(.system(size: 13, weight: .bold))
                                    .foregroundColor(BBColors.textSecondary)
                                    .multilineTextAlignment(.center)
                            }
                            
                            // Form fields
                            VStack(spacing: 16) {
                                // Email field with envelope icon prefix
                                HStack(spacing: 12) {
                                    Image(systemName: "envelope.fill")
                                        .foregroundColor(focusedField == .email ? BBColors.primary : BBColors.textSecondary)
                                        .font(.system(size: 16))
                                        .frame(width: 24)
                                    
                                    TextField("Email", text: $email)
                                        .textContentType(.emailAddress)
                                        .keyboardType(.emailAddress)
                                        .textInputAutocapitalization(.never)
                                        .autocorrectionDisabled()
                                        .focused($focusedField, equals: .email)
                                }
                                .bbInput(isFocused: focusedField == .email)
                                
                                // Password field with lock prefix + eye toggle show/hide
                                HStack(spacing: 12) {
                                    Image(systemName: "lock.fill")
                                        .foregroundColor(focusedField == .password ? BBColors.primary : BBColors.textSecondary)
                                        .font(.system(size: 16))
                                        .frame(width: 24)
                                    
                                    if showPassword {
                                        TextField("Password", text: $password)
                                            .textContentType(.password)
                                            .focused($focusedField, equals: .password)
                                    } else {
                                        SecureField("Password", text: $password)
                                            .textContentType(.password)
                                            .focused($focusedField, equals: .password)
                                    }
                                    
                                    Button {
                                        showPassword.toggle()
                                    } label: {
                                        Image(systemName: showPassword ? "eye.slash.fill" : "eye.fill")
                                            .foregroundColor(BBColors.textSecondary)
                                            .font(.system(size: 16))
                                    }
                                }
                                .bbInput(isFocused: focusedField == .password)
                            }
                            
                            // Forgot password link
                            HStack {
                                Spacer()
                                Button {
                                    if let url = URL(string: "https://titan.csit.rmit.edu.au/~s3974781/bitbalance/reset-password.php") {
                                        openURL(url)
                                    }
                                } label: {
                                    Text("Forgot password?")
                                        .font(.system(size: 13, weight: .bold))
                                        .foregroundColor(BBColors.primary)
                                }
                            }
                            .padding(.top, -8)
                            
                            // Styled dynamic error message
                            if let errorMessage = session.errorMessage {
                                HStack(spacing: 10) {
                                    Image(systemName: "exclamationmark.triangle.fill")
                                        .font(.system(size: 16, weight: .bold))
                                    Text(errorMessage)
                                        .font(.system(size: 13, weight: .bold))
                                }
                                .frame(maxWidth: .infinity, alignment: .leading)
                                .bbAlert(isSuccess: false)
                                .transition(.opacity.combined(with: .scale))
                            }
                            
                            // Bouncy 3D Login Button
                            Button {
                                focusedField = nil
                                Task {
                                    await session.signIn(email: email, password: password)
                                }
                            } label: {
                                if session.isLoading {
                                    ProgressView()
                                        .tint(.white)
                                        .frame(maxWidth: .infinity)
                                } else {
                                    Text("Log In")
                                        .fontWeight(.bold)
                                        .frame(maxWidth: .infinity)
                                }
                            }
                            .buttonStyle(BBButtonStyle(
                                backgroundColor: BBColors.primary,
                                shadowColor: BBColors.primaryHover,
                                isEnabled: !email.isEmpty && !password.isEmpty && !session.isLoading
                            ))
                            .disabled(session.isLoading || email.isEmpty || password.isEmpty)
                            
                            // Signup redirect link below button
                            HStack(spacing: 4) {
                                Text("Don't have an account?")
                                    .font(.system(size: 13, weight: .bold))
                                    .foregroundColor(BBColors.textSecondary)
                                Button {
                                    if let url = URL(string: "https://titan.csit.rmit.edu.au/~s3974781/bitbalance/") {
                                        openURL(url)
                                    }
                                } label: {
                                    Text("Sign up")
                                        .font(.system(size: 13, weight: .black))
                                        .foregroundColor(BBColors.primary)
                                }
                            }
                            .padding(.top, 4)
                        }
                        .bbCard(radius: BBRadius.xl, padding: 24)
                        
                        Spacer()
                    }
                    .padding(20)
                }
            }
        }
    }
}
