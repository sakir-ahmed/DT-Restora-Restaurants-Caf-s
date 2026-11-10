<?php

declare(strict_types=1);

/*
 * Demo site text. Loaded on top of lang/en/legal.php when IS_DEMO=true, so the
 * demo shows the neutral DT Restora wording and the production site keeps
 * the Arman's Kitchen text.
 */
return [
    'contact_line' => 'Questions? Call your nearest branch or visit our contact page.',
    'privacy' => [
        'intro' => 'How :app collects, uses and protects your personal information when you use our website and order from our branches.',
        's1_body' => ':app runs restaurant and cafe branches and this website. This policy covers the information you give us online and when you order from any of our branches.',
        's2_body' => 'Details you give us: your name, mobile number, optional email, delivery addresses, order history, loyalty points and anything you write in a job application.

Payments are handled by bKash, Nagad and SSLCommerz. We never see or store your card number or wallet PIN.

Basic technical data, such as device type and the pages you visit, is used to keep the site working and secure.',
        's3_body' => 'To take, prepare and deliver your orders; to send order updates and one-time verification codes by SMS; to run loyalty rewards and coupons; to answer your questions; to review job applications; to prevent fraud and misuse; and to meet our legal and accounting duties.',
        's4_body' => 'Only the people who need it to serve you: the branch preparing your order and the rider delivering it (your name, phone and address), our payment providers, our SMS provider and our technology and hosting providers.

We may also share information when the law requires it. We never sell your personal information.',
        's7_body' => 'You can see and update your details and saved addresses in your account at any time.

You can delete your data from your account with Delete my data. Your name and contact details are removed, and past orders stay without your identity because we must keep sales records.

To stop promotional messages or ask for a copy of your information, contact your nearest branch.',
        's9_body' => 'We may update this policy as our services change and will publish the new version on this page. For questions about your information, call your nearest branch or use our contact page.',
    ],
    'terms' => [
        'intro' => 'The rules for using the :app website and ordering from our branches.',
        's2_body' => 'Menu items, prices and availability can change without notice. The price you see at checkout, including taxes, service charge and delivery fee, is the price you pay.

An order is confirmed when the branch accepts it. We may decline or cancel an order, for example when an item is unavailable, and we will tell you and refund any payment.',
        's3_body' => 'You can pay online with bKash, Nagad or a card through SSLCommerz, or pay cash on delivery in covered areas. Online payments are processed by those providers under their own terms. You agree to use only payment methods that belong to you.',
        's4_body' => 'Delivery areas, fees, minimum orders and estimated times are shown for each branch and at checkout. Times are estimates. Please read our delivery information for details.',
        's10_body' => 'We may update these terms and will publish the new version here. These terms are governed by the laws of Bangladesh. For questions, call your nearest branch or use our contact page.',
    ],
    'refund' => [
        's1_body' => 'You can cancel an order any time before the kitchen accepts it. After that the food is being prepared, so please call the branch straight away and we will do our best to help.',
        's2_body' => 'If an item is missing, incorrect or not up to standard, tell the branch the same day and have your order number ready. Photos help. We will offer a replacement, credit or refund depending on the problem.',
        's4_body' => 'Refunds go back to the original payment method: your bKash or Nagad account or your card. The time it takes depends on the payment provider or your bank.',
        's5_body' => 'When you pay cash on delivery nothing is charged in advance, so a cancelled order needs no refund. If you were charged for a problem order, we will settle it with you through the branch.',
        's6_body' => 'Call the branch you ordered from. You will find phone numbers on our contact page and on each branch page.',
    ],
    'delivery' => [
        's1_body' => 'Each branch delivers to its own list of areas. You can see them on the branch page and when you choose your area at checkout. If your area is not listed, you can still order for takeaway or dine in.',
        's4_body' => 'You can choose a later time for your order at checkout. Please pick a time within the branch opening hours.',
        's6_body' => 'You can order online and collect at the branch, or scan the QR code on your table to order from your seat. Table orders are served at the table.',
    ],
];
