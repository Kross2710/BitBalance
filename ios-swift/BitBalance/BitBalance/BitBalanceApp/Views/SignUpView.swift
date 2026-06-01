import SwiftUI

struct SignUpView: View {
    @EnvironmentObject private var session: SessionStore
    @Environment(\.dismiss) private var dismiss
    @Environment(\.openURL) private var openURL

    @State private var firstName = ""
    @State private var lastName = ""
    @State private var email = ""
    @State private var password = ""
    @State private var confirmPassword = ""
    @State private var acceptedTerms = false

    @FocusState private var focusedField: Field?
    enum Field {
        case firstName
        case lastName
        case email
        case password
        case confirmPassword
    }

    // Mirrors api/auth/register.php + include/handlers/user_signup.php rules.
    private var passwordValid: Bool {
        password.count >= 8
            && password.range(of: "[a-z]", options: .regularExpression) != nil
            && password.range(of: "[A-Z]", options: .regularExpression) != nil
            && password.range(of: "\\d", options: .regularExpression) != nil
    }

    private var passwordsMatch: Bool {
        confirmPassword.isEmpty || password == confirmPassword
    }

    private var isValid: Bool {
        !firstName.trimmingCharacters(in: .whitespaces).isEmpty
            && !lastName.trimmingCharacters(in: .whitespaces).isEmpty
            && !email.trimmingCharacters(in: .whitespaces).isEmpty
            && passwordValid
            && password == confirmPassword
            && acceptedTerms
    }

    var body: some View {
        ZStack {
            BBColors.backgroundGradient
                .ignoresSafeArea()

            ScrollView(showsIndicators: false) {
                VStack {
                    Spacer(minLength: 24)

                    VStack(spacing: 0) {
                        AuthHeroBanner(
                            title: "Join the crew.",
                            subtitle: "Log meals, earn XP, and climb the leaderboard with friends."
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
        .overlay(alignment: .topLeading) {
            Button {
                dismiss()
            } label: {
                Image(systemName: "xmark.circle.fill")
                    .font(.system(size: 28))
                    .foregroundStyle(.white, .black.opacity(0.35))
                    .padding(16)
            }
        }
        .onDisappear {
            session.errorMessage = nil
        }
    }

    // MARK: - Form (css/login.css .form-section + css/pages/signup.css)

    private var formSection: some View {
        VStack(alignment: .leading, spacing: 16) {
            VStack(alignment: .leading, spacing: 6) {
                Text("Create your account")
                    .font(BBFont.displayBold)
                    .foregroundColor(BBColors.text)
                Text("Start your first streak in under a minute.")
                    .font(BBFont.font(BBFont.base, .medium))
                    .foregroundColor(BBColors.textSecondary)
            }
            .padding(.bottom, 6)

            // First / Last name side by side (.form-row)
            HStack(spacing: 12) {
                TextField("First Name", text: $firstName)
                    .textContentType(.givenName)
                    .textInputAutocapitalization(.words)
                    .focused($focusedField, equals: .firstName)
                    .bbInput(isFocused: focusedField == .firstName)

                TextField("Last Name", text: $lastName)
                    .textContentType(.familyName)
                    .textInputAutocapitalization(.words)
                    .focused($focusedField, equals: .lastName)
                    .bbInput(isFocused: focusedField == .lastName)
            }

            TextField("Email", text: $email)
                .textContentType(.emailAddress)
                .keyboardType(.emailAddress)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .focused($focusedField, equals: .email)
                .bbInput(isFocused: focusedField == .email)

            SecureField("Password", text: $password)
                .textContentType(.newPassword)
                .focused($focusedField, equals: .password)
                .bbInput(isFocused: focusedField == .password)

            // Password requirement hint (.password-requirements, .is-valid)
            passwordHint

            SecureField("Confirm Password", text: $confirmPassword)
                .textContentType(.newPassword)
                .focused($focusedField, equals: .confirmPassword)
                .bbInput(isFocused: focusedField == .confirmPassword)

            if !passwordsMatch {
                Text("Passwords do not match")
                    .font(BBFont.font(13, .semibold))
                    .foregroundColor(BBColors.danger)
            }

            termsRow

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
                    await session.signUp(
                        firstName: firstName.trimmingCharacters(in: .whitespaces),
                        lastName: lastName.trimmingCharacters(in: .whitespaces),
                        email: email.trimmingCharacters(in: .whitespaces),
                        password: password,
                        confirmPassword: confirmPassword
                    )
                }
            } label: {
                if session.isLoading {
                    ProgressView()
                        .tint(.white)
                        .frame(maxWidth: .infinity)
                } else {
                    Text("Create Account")
                        .frame(maxWidth: .infinity)
                }
            }
            .buttonStyle(BBButtonStyle(
                backgroundColor: BBColors.primary,
                shadowColor: BBColors.primaryHover,
                isEnabled: isValid && !session.isLoading
            ))
            .disabled(!isValid || session.isLoading)
            .padding(.top, 2)

            HStack(spacing: 4) {
                Text("Already have an account?")
                    .font(BBFont.font(BBFont.sm, .medium))
                    .foregroundColor(BBColors.textSecondary)
                Button {
                    dismiss()
                } label: {
                    Text("Sign In")
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

    // MARK: - Pieces

    private var passwordHint: some View {
        HStack(spacing: 6) {
            Image(systemName: passwordValid ? "checkmark.circle.fill" : "info.circle")
                .font(BBFont.captionBold)
            Text(passwordValid
                 ? "Password meets all requirements"
                 : "At least 8 characters, with an uppercase, lowercase letter and a number.")
                .font(BBFont.font(13, .semibold))
                .fixedSize(horizontal: false, vertical: true)
        }
        .foregroundColor(passwordValid ? BBColors.success : BBColors.textSecondary)
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(.top, -6)
    }

    private var termsRow: some View {
        HStack(alignment: .top, spacing: 10) {
            Button {
                acceptedTerms.toggle()
            } label: {
                Image(systemName: acceptedTerms ? "checkmark.square.fill" : "square")
                    .font(.system(size: 20))
                    .foregroundColor(acceptedTerms ? BBColors.primary : BBColors.textMuted)
            }

            // Thay vì dùng onTapGesture, ta dùng Link bọc ngoài Text
            if let url = URL(string: "https://titan.csit.rmit.edu.au/~s3974781/bitbalance/terms.php") {
                Link(destination: url) {
                    Text("I agree to the \(Text("Terms and Conditions").foregroundColor(BBColors.primary).fontWeight(.bold))")
                        .foregroundColor(BBColors.textSecondary)
                        .font(BBFont.font(BBFont.sm, .medium))
                        .fixedSize(horizontal: false, vertical: true)
                        .multilineTextAlignment(.leading) // Đảm bảo text căn lề trái nếu xuống dòng
                }
            }

            Spacer(minLength: 0)
        }
    }
}

// MARK: - Previews
#Preview("Form trống") {
    SignUpView()
        .environmentObject(SessionStore.preview)
}

#Preview("Có lỗi server") {
    SignUpView()
        .environmentObject(SessionStore.previewWithError("Email này đã được đăng ký!"))
}
