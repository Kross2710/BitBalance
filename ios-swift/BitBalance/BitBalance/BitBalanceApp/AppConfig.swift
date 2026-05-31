import Foundation

enum AppConfig {
    // iOS Simulator can reach the Mac's XAMPP server through localhost.
//    static let baseURL = URL(string: "http://localhost/BitBalance-2.0---Calorie-Tracker")!

    // For a real iPhone on the same Wi-Fi, use your Mac LAN IP instead:
    // static let baseURL = URL(string: "http://192.168.1.10/BitBalance-2.0---Calorie-Tracker")!

    // Production:
     static let baseURL = URL(string: "https://titan.csit.rmit.edu.au/~s3974781/bitbalance")!
}

