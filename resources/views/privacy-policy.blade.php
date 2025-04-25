@extends('layouts.app')

@section('title', 'Privacy Policy - ' . config('app.name'))

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h1 class="h4 mb-0">Privacy Policy</h1>
                    </div>

                    <div class="card-body">
                        <p class="text-muted">Last updated: {{ now()->format('F j, Y') }}</p>

                        <div class="policy-content">
                            <h2 class="h5 mt-4">1. Introduction</h2>
                            <p>Welcome to {{ config('app.name') }} ("we," "our," or "us"). We are committed to protecting
                                your personal information and your right to privacy. If you have any questions or concerns
                                about this privacy policy, please contact us at {{ config('app.email') }}.</p>

                            <h2 class="h5 mt-4">2. Information We Collect</h2>
                            <p>We collect personal information that you voluntarily provide to us when you register on the
                                app, express an interest in obtaining information about us or our products and services, or
                                otherwise when you contact us.</p>
                            <p>The personal information we collect may include:</p>
                            <ul>
                                <li>Name and contact details (email, phone number, address)</li>
                                <li>Government-issued identification</li>
                                <li>Financial information (bank account details, transaction history)</li>
                                <li>Device and usage data (IP address, browser type, operating system)</li>
                            </ul>

                            <h2 class="h5 mt-4">3. How We Use Your Information</h2>
                            <p>We use personal information collected via our app for a variety of business purposes,
                                including:</p>
                            <ul>
                                <li>To facilitate account creation and logon process</li>
                                <li>To process loan applications and transactions</li>
                                <li>To enforce our terms, conditions, and policies</li>
                                <li>To respond to legal requests and prevent harm</li>
                                <li>For other business purposes like data analysis and improving our services</li>
                            </ul>

                            <h2 class="h5 mt-4">4. Sharing Your Information</h2>
                            <p>We only share information with your consent, to comply with laws, or with third-party vendors
                                who perform services for us (like KYC verification, payment processing, and data analysis).
                            </p>

                            <h2 class="h5 mt-4">5. Data Security</h2>
                            <p>We implement appropriate technical and organizational security measures to protect your
                                personal information. However, please remember that no electronic transmission over the
                                internet is 100% secure.</p>

                            <h2 class="h5 mt-4">6. Your Privacy Rights</h2>
                            <p>Depending on your location, you may have rights to:</p>
                            <ul>
                                <li>Access, update, or delete your information</li>
                                <li>Object to processing of your personal data</li>
                                <li>Request restriction of processing</li>
                                <li>Request data portability</li>
                            </ul>

                            <h2 class="h5 mt-4">7. Updates to This Policy</h2>
                            <p>We may update this privacy policy from time to time. The updated version will be indicated by
                                an updated "Last updated" date.</p>

                            <h2 class="h5 mt-4">8. Contact Us</h2>
                            <p>If you have questions or comments about this policy, email us at {{ config('app.email') }} or
                                write to us at:</p>
                            <address>
                                {{ config('app.name') }},<br>
                                Kampala,<br>
                                Uganda
                            </address>
                        </div>
                        <h2 class="h5 mt-4">Account Deletion</h2>
                        <p>You may request to delete your account at any time. When you delete your account:</p>
                        <ul>
                            <li>Your personal information will be anonymized</li>
                            <li>Financial records will be retained as required by law</li>
                            <li>Any pending transactions will be cancelled</li>
                        </ul>
                        <p><a href="{{ route('account.delete') }}" class="btn btn-sm btn-outline-danger">Delete My
                                Account</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
