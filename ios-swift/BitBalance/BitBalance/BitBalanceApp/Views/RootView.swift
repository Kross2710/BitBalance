import SwiftUI

struct RootView: View {
    @EnvironmentObject private var session: SessionStore

    var body: some View {
        Group {
            if session.isLoading && session.user == nil {
                ProgressView()
            } else if session.user == nil {
                LoginView()
            } else {
                MainTabView()
            }
        }
        .task {
            if session.user == nil {
                await session.restoreSession()
            }
        }
    }
}
