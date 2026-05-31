import SwiftUI

struct RootView: View {
    @EnvironmentObject private var session: SessionStore

    var body: some View {
        Group {
            if session.isLoading && session.user == nil && !session.isGuest {
                // Cold-start session restore
                ZStack {
                    BBColors.backgroundGradient.ignoresSafeArea()
                    VStack(spacing: 16) {
                        Text("🥗").font(.system(size: 56))
                        ProgressView().tint(BBColors.primary)
                    }
                }
            } else if session.user == nil && !session.isGuest {
                // Not authenticated, not guest → landing screen
                AuthEntryView()
            } else if session.user != nil && session.needsOnboarding {
                // New signup → onboarding wizard before first dashboard access
                OnboardingView()
            } else {
                // Authenticated or guest → app
                MainTabView()
            }
        }
        .task {
            if session.user == nil && !session.isGuest {
                await session.restoreSession()
            }
        }
    }
}
