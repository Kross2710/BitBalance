import SwiftUI

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

