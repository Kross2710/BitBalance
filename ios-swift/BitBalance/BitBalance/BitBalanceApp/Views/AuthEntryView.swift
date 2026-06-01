import SwiftUI

// MARK: - Auth Entry (Landing screen)
// Architecture note: OAuth providers are modelled as an enum so adding
// Google / Apple later is one new case + one new button — no structural change.

struct AuthEntryView: View {
    @EnvironmentObject private var session: SessionStore

    // Single sheet driven by an enum — two separate `.sheet` modifiers on the
    // same view conflict (SwiftUI only honors the last one), which made
    // "Sign In" unresponsive.
    enum Sheet: Identifiable {
        case login, signUp
        var id: Int { hashValue }
    }
    @State private var activeSheet: Sheet?

    var body: some View {
        VStack(spacing: 0) {
            heroSection           // flexible — fills the space above the card
            authCard              // natural height, sits below the hero
        }
        .ignoresSafeArea(edges: .top)   // photo extends under the status bar
        .background(BBColors.surface.ignoresSafeArea())   // surface fills under home indicator too
        .sheet(item: $activeSheet) { sheet in
            switch sheet {
            case .login:  LoginView()
            case .signUp: SignUpView()
            }
        }
    }

    // MARK: - Hero

    private var heroSection: some View {
        ZStack(alignment: .bottomLeading) {
            // Brand tint (fills the hero — does not dictate layout size)
            LinearGradient(
                gradient: Gradient(colors: [
                    Color(hex: "58CC02").opacity(0.30),
                    Color(hex: "1CB0F6").opacity(0.25)
                ]),
                startPoint: .topLeading, endPoint: .bottomTrailing
            )

            // bottom-to-top scrim so text is always readable
            LinearGradient(
                gradient: Gradient(stops: [
                    .init(color: Color.black.opacity(0.85), location: 0.00),
                    .init(color: Color.black.opacity(0.45), location: 0.35),
                    .init(color: Color.clear,               location: 0.65)
                ]),
                startPoint: .bottom, endPoint: .top
            )

            // logo + tagline — aligns to the framed hero, not the overflowing image
            VStack(alignment: .leading, spacing: 8) {
                HStack(spacing: 10) {
                    Text("BitBalance")
                        .font(BBFont.displayBold)
                        .foregroundColor(.white)
                }
                Text("Track. Earn XP. Level up.")
                    .font(BBFont.font(17, .heavy))
                    .foregroundColor(.white)
                Text("Your wellness journey, gamified.")
                    .font(BBFont.font(BBFont.sm, .medium))
                    .foregroundColor(.white.opacity(0.88))
            }
            .shadow(color: .black.opacity(0.4), radius: 6, x: 0, y: 1)
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(.horizontal, 28)
            .padding(.bottom, 44)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        // Photo lives in the background so scaledToFill overflow is clipped here
        // without affecting the ZStack's layout bounds (which kept the text left-cut).
        .background(
            Image("food")
                .resizable()
                .scaledToFill()
        )
        .clipped()
    }

    // MARK: - Auth card

    private var authCard: some View {
        VStack(spacing: 12) {
            // ── Primary email CTAs ───────────────────────────────────
            Button {
                activeSheet = .signUp
            } label: {
                Text("Create Account")
                    .frame(maxWidth: .infinity)
            }
            .buttonStyle(BBButtonStyle(
                backgroundColor: BBColors.primary,
                shadowColor: BBColors.primaryHover
            ))

            Button {
                activeSheet = .login
            } label: {
                Text("Sign In")
                    .frame(maxWidth: .infinity)
                    .font(BBFont.bodyBold)
                    .foregroundColor(BBColors.text)
                    .padding(.vertical, 14)
                    .background(BBColors.surfaceAlt)
                    .cornerRadius(BBRadius.md)
                    .overlay(
                        RoundedRectangle(cornerRadius: BBRadius.md)
                            .stroke(BBColors.border, lineWidth: 2)
                    )
                    .background(
                        RoundedRectangle(cornerRadius: BBRadius.md)
                            .fill(BBColors.borderSubtle)
                            .offset(y: 4)
                    )
            }

            // ── Divider ──────────────────────────────────────────────
            HStack(spacing: 12) {
                Rectangle().fill(BBColors.border).frame(height: 1)
                Text("or").font(BBFont.font(13, .bold)).foregroundColor(BBColors.textMuted)
                Rectangle().fill(BBColors.border).frame(height: 1)
            }
            .padding(.vertical, 2)

            // ── OAuth slots ──────────────────────────────────────────
            // Design: provider enum drives both button appearance and action.
            // Enable each provider by flipping `isAvailable` when the SDK is wired.
            OAuthProviderButton(provider: .apple,  isAvailable: false) { /* TODO: Apple Sign In */ }
            OAuthProviderButton(provider: .google, isAvailable: false) { /* TODO: Google Sign In */ }

            // ── Guest ────────────────────────────────────────────────
            Button {
                session.continueAsGuest()
            } label: {
                Text("Continue as Guest")
                    .font(BBFont.font(BBFont.sm, .bold))
                    .foregroundColor(BBColors.textSecondary)
                    .underline()
            }
            .padding(.top, 2)
        }
        .padding(.horizontal, 24)
        .padding(.top, 24)
        .padding(.bottom, 12)
        .frame(maxWidth: .infinity)
        .background(BBColors.surface)
        // Round only the top corners and lift the card up over the photo edge.
        .clipShape(.rect(topLeadingRadius: BBRadius.xl, topTrailingRadius: BBRadius.xl))
        .shadow(color: .black.opacity(0.18), radius: 20, x: 0, y: -4)
        .padding(.top, -24)
    }
}

// MARK: - OAuth provider button
// Adding a new provider = new enum case + logo asset. Everything else (layout,
// "coming soon" badge, disabled state) is handled by this view automatically.

enum OAuthProvider {
    case apple
    case google
    // case github // future

    var label: String {
        switch self {
        case .apple:  return "Sign in with Apple"
        case .google: return "Sign in with Google"
        }
    }

    var logo: String {
        // SF Symbol for Apple; fallback text badge for Google until real asset added
        switch self {
        case .apple:  return "apple.logo"
        case .google: return "globe"   // swap to "google.logo" once asset added
        }
    }

    var backgroundColor: Color {
        switch self {
        case .apple:  return Color(hex: "000000")
        case .google: return Color(hex: "FFFFFF")
        }
    }

    var foregroundColor: Color {
        switch self {
        case .apple:  return .white
        case .google: return Color(hex: "1E2937")
        }
    }
}

private struct OAuthProviderButton: View {
    let provider: OAuthProvider
    let isAvailable: Bool
    let action: () -> Void

    var body: some View {
        Button {
            guard isAvailable else { return }
            action()
        } label: {
            HStack(spacing: 10) {
                Image(systemName: provider.logo)
                    .font(BBFont.font(BBFont.lg, .semibold))
                Text(provider.label)
                    .font(BBFont.font(15, .bold))
                Spacer()
                if !isAvailable {
                    Text("Coming soon")
                        .font(.system(size: 11, weight: .heavy))
                        .foregroundColor(BBColors.textMuted)
                        .padding(.horizontal, 8)
                        .padding(.vertical, 4)
                        .background(BBColors.surfaceAlt)
                        .cornerRadius(BBRadius.pill)
                        .overlay(
                            RoundedRectangle(cornerRadius: BBRadius.pill)
                                .stroke(BBColors.border, lineWidth: 1)
                        )
                }
            }
            .foregroundColor(isAvailable ? provider.foregroundColor : BBColors.textSecondary)
            .padding(.vertical, 13)
            .padding(.horizontal, 16)
            .frame(maxWidth: .infinity)
            .background(isAvailable ? provider.backgroundColor : BBColors.surfaceAlt)
            .cornerRadius(BBRadius.md)
            .overlay(
                RoundedRectangle(cornerRadius: BBRadius.md)
                    .stroke(BBColors.border, lineWidth: 2)
            )
            .opacity(isAvailable ? 1.0 : 0.65)
        }
        .disabled(!isAvailable)
    }
}

// MARK: - Previews
#Preview("Entry") {
    AuthEntryView()
        .environmentObject(SessionStore.preview)
}
