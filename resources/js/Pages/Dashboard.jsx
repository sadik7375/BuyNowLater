import React, { useState, useEffect } from 'react';
import axios from 'axios';
import {
    Page,
    Layout,
    Card,
    Grid,
    BlockStack,
    InlineStack,
    Text,
    Button,
    Badge,
    Banner,
    Tabs,
    TextField,
    Select,
    IndexTable,
    Icon,
    Spinner,
    Box,
    Divider,
    FormLayout,
    Checkbox,
    Modal,
} from '@shopify/polaris';
import {
    OrderIcon,
    SettingsIcon,
    InfoIcon,
    ExportIcon,
    SendIcon,
    RefreshIcon,
    AlertCircleIcon,
    CheckCircleIcon,
    CheckIcon,
    SearchIcon,
} from '@shopify/polaris-icons';
import { router, usePage } from '@inertiajs/react';

export default function Dashboard(props) {
    const {
        settings = {},
        bookings = [],
        reminders = [],
        subscribers = [],
        revenueRecovered = 0,
        activeBookings = 0,
        expiringSoonCount = 0,
        alertSubscribersCount = 0,
        wishes = {},
        expiringToday = [],
        expiringTomorrow = [],
        expiringThisWeek = [],
        statusCounts = { pending: 0, deposit_paid: 0, completed: 0, expired: 0 },
        targetedProducts = [],
        flash = {},
        activeTab: serverActiveTab = 'tab-overview',
        hasPlan = false,
        shopName = '',
        shopEmail = '',
    } = props;

    // Mapping tab strings to index
    const tabMap = {
        'tab-overview': 0,
        'tab-bookings-list': 1,
        'tab-pricing': 2,
        'tab-price-plan': 2,
        'price-plan': 2,
        'pricing': 2,
        'tab-settings': 3,
        'tab-support': 4,
    };
    const indexTabMap = ['tab-overview', 'tab-bookings-list', 'tab-pricing', 'tab-settings', 'tab-support'];

    const [selectedTab, setSelectedTab] = useState(tabMap[serverActiveTab] || 0);

    // Settings form state
    const [depositPercentage, setDepositPercentage] = useState(settings.deposit_percentage || 10);
    const [holdDurationDays, setHoldDurationDays] = useState(settings.hold_duration_days || 14);
    const [buttonText, setButtonText] = useState(settings.button_text || 'Buy Later — not ready yet?');
    const [senderDisplayName, setSenderDisplayName] = useState(settings.sender_display_name || '');
    const [productTargetingType, setProductTargetingType] = useState(settings.product_targeting_type || 'all');
    const [targetedProductIds, setTargetedProductIds] = useState(settings.targeted_product_ids || '');
    const [isSaving, setIsSaving] = useState(false);

    // Product search state for Product Targeting selector
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [isSearching, setIsSearching] = useState(false);
    const [selectedProductsList, setSelectedProductsList] = useState(targetedProducts || []);

    // Free plan limit check (10 deposit reservations)
    const isFreeLimitReached = !hasPlan && (bookings.length >= 10 || activeBookings >= 10);

    // Booking action loading state
    const [actionLoading, setActionLoading] = useState({});

    // Feedback Form state
    const [feedbackType, setFeedbackType] = useState('General Feedback');
    const [feedbackContact, setFeedbackContact] = useState(shopEmail || '');
    const [feedbackSubject, setFeedbackSubject] = useState('');
    const [feedbackMessage, setFeedbackMessage] = useState('');
    const [isSubmittingFeedback, setIsSubmittingFeedback] = useState(false);
    const [feedbackSuccess, setFeedbackSuccess] = useState(false);
    const [feedbackError, setFeedbackError] = useState('');

    useEffect(() => {
        if (shopEmail && !feedbackContact) {
            setFeedbackContact(shopEmail);
        }
    }, [shopEmail]);

    const handleFeedbackSubmit = async (e) => {
        e.preventDefault();
        if (!feedbackSubject.trim() || !feedbackMessage.trim()) {
            setFeedbackError('Please enter both subject and message before submitting.');
            return;
        }

        setIsSubmittingFeedback(true);
        setFeedbackError('');
        setFeedbackSuccess(false);

        try {
            const token = await getSessionToken();
            const headers = token ? { Authorization: `Bearer ${token}` } : {};

            const res = await axios.post(
                '/admin/feedback',
                {
                    feedback_type: feedbackType,
                    feedback_contact: feedbackContact,
                    feedback_subject: feedbackSubject,
                    feedback_message: feedbackMessage,
                },
                { headers }
            );

            if (res.data && res.data.success) {
                setFeedbackSuccess(true);
                setFeedbackSubject('');
                setFeedbackMessage('');
            } else {
                setFeedbackError(res.data?.message || 'Failed to send feedback.');
            }
        } catch (err) {
            setFeedbackError(err.response?.data?.message || 'Unable to submit feedback. Please try again.');
        } finally {
            setIsSubmittingFeedback(false);
        }
    };

    const tabs = [
        { id: 'tab-overview', content: 'Overview', icon: OrderIcon },
        { id: 'tab-bookings-list', content: `Bookings (${bookings.length})`, icon: OrderIcon },
        { id: 'tab-pricing', content: 'Price Plan', icon: InfoIcon },
        { id: 'tab-settings', content: 'App Settings', icon: SettingsIcon },
        { id: 'tab-support', content: 'Help & Support', icon: InfoIcon },
    ];

    const handleTabChange = (selectedTabIndex) => {
        setSelectedTab(selectedTabIndex);
    };

    // Handle Product Search
    useEffect(() => {
        if (!searchQuery || searchQuery.trim().length < 2) {
            setSearchResults([]);
            return;
        }
        const timer = setTimeout(() => {
            setIsSearching(true);
            fetch(`/admin/products/search?q=${encodeURIComponent(searchQuery)}`)
                .then((res) => res.json())
                .then((data) => {
                    setSearchResults(data || []);
                    setIsSearching(false);
                })
                .catch(() => setIsSearching(false));
        }, 300);
        return () => clearTimeout(timer);
    }, [searchQuery]);

    const handleSelectProduct = (prod) => {
        if (selectedProductsList.some((p) => String(p.id) === String(prod.id))) return;
        const updated = [...selectedProductsList, prod];
        setSelectedProductsList(updated);
        setTargetedProductIds(updated.map((p) => p.id).join(','));
        setSearchQuery('');
        setSearchResults([]);
    };

    const handleRemoveProduct = (prodId) => {
        const updated = selectedProductsList.filter((p) => String(p.id) !== String(prodId));
        setSelectedProductsList(updated);
        setTargetedProductIds(updated.map((p) => p.id).join(','));
    };

    // Helper to get App Bridge v3 Session Token
    const getSessionToken = async () => {
        if (window.shopify && typeof window.shopify.idToken === 'function') {
            try {
                return await window.shopify.idToken();
            } catch (e) {
                console.warn('Failed to get shopify idToken:', e);
            }
        }
        return null;
    };

    // Handle Settings Submit
    const handleSaveSettings = async (e) => {
        if (e) e.preventDefault();
        setIsSaving(true);
        const token = await getSessionToken();
        const headers = token ? { Authorization: `Bearer ${token}` } : {};

        router.post(
            '/admin/settings',
            {
                deposit_percentage: depositPercentage,
                hold_duration_days: holdDurationDays,
                button_text: buttonText,
                sender_display_name: senderDisplayName,
                product_targeting_type: productTargetingType,
                targeted_product_ids: targetedProductIds,
                targeted_products_json: JSON.stringify(selectedProductsList),
            },
            {
                headers,
                onFinish: () => setIsSaving(false),
            }
        );
    };

    // Handle Sending Balance Link / Reminder
    const handleSendAction = async (bookingId, actionType) => {
        setActionLoading((prev) => ({ ...prev, [bookingId]: true }));
        const endpoint = actionType === 'link' ? `/admin/bookings/${bookingId}/send-balance-link` : `/admin/bookings/${bookingId}/send-reminder`;
        const token = await getSessionToken();
        const headers = token ? { Authorization: `Bearer ${token}` } : {};

        router.post(
            endpoint,
            {},
            {
                headers,
                onFinish: () => setActionLoading((prev) => ({ ...prev, [bookingId]: false })),
            }
        );
    };

    const formatOrderNumber = (b) => {
        const raw = b.order_name || b.balance_order_name || b.draft_order_name;
        if (raw) {
            return raw.startsWith('#') ? raw : `#${raw}`;
        }
        if (b.order_id) {
            return `#${b.order_id}`;
        }
        return `#${b.id}`;
    };

    const getOrderAdminUrl = (b) => {
        const rawId = b.order_id || b.draft_order_id;
        if (!rawId) return null;
        const cleanId = String(rawId).replace(/[^0-9]/g, '');
        if (!cleanId) return null;

        const myshopifyDomain = shopName ? (shopName.includes('.myshopify.com') ? shopName : `${shopName}.myshopify.com`) : '';
        const isDraft = !b.order_id && b.draft_order_id;
        const path = isDraft ? 'draft_orders' : 'orders';

        if (myshopifyDomain) {
            return `https://${myshopifyDomain}/admin/${path}/${cleanId}`;
        }
        return `/admin/${path}/${cleanId}`;
    };

    const handleExportCSV = () => {
        if (!bookings || bookings.length === 0) return;

        const headers = ['Order Name', 'Customer Email', 'Date', 'Product', 'Deposit Paid ($)', 'Balance Due ($)', 'Payment Status'];
        const rows = bookings.map((b) => [
            `"${formatOrderNumber(b).replace(/"/g, '""')}"`,
            `"${(b.email || '').replace(/"/g, '""')}"`,
            `"${b.created_at ? new Date(b.created_at).toLocaleDateString() : ''}"`,
            `"${(b.product_title || '').replace(/"/g, '""')}"`,
            `"${Number(b.deposit_amount || 0).toFixed(2)}"`,
            `"${Number(b.remaining_balance || 0).toFixed(2)}"`,
            `"${(b.payment_status || b.status || '').replace(/"/g, '""')}"`,
        ]);

        const csvContent = [headers.join(','), ...rows.map((row) => row.join(','))].join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', `buy_now_later_orders_${new Date().toISOString().slice(0, 10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };

    const renderPaymentStatusBadge = (booking) => {
        const status = (booking.payment_status || booking.status || '').toLowerCase();
        if (status === 'partially_paid' || status === 'partially paid' || booking.status === 'deposit_paid') {
            return <Badge tone="warning">Partially paid</Badge>;
        }
        if (status === 'paid' || booking.status === 'completed') {
            return <Badge tone="success">Paid</Badge>;
        }
        if (booking.status === 'expired') {
            return <Badge tone="critical">Expired</Badge>;
        }
        return <Badge tone="subdued">Unpaid</Badge>;
    };

    const renderFulfillmentStatusBadge = (booking) => {
        const status = (booking.fulfillment_status || '').toLowerCase();
        if (status === 'on_hold' || status === 'on hold' || booking.status === 'deposit_paid') {
            return <Badge tone="attention">On hold</Badge>;
        }
        if (status === 'fulfilled' || booking.status === 'completed') {
            return <Badge tone="info">Fulfilled</Badge>;
        }
        return <Badge tone="subdued">Unfulfilled</Badge>;
    };

    return (
        <Page
            title="Buy Now Later"
            subtitle="Manage deferred purchase options, deposits, and customer balance collection."
            compactTitle
        >
            <BlockStack gap="400">
                {/* Flash Messages */}
                {flash.success && (
                    <Banner tone="success" onDismiss={() => {}}>
                        <p>{flash.success}</p>
                    </Banner>
                )}
                {flash.error && (
                    <Banner tone="critical" onDismiss={() => {}}>
                        <p>{flash.error}</p>
                    </Banner>
                )}

                {/* Free Plan Limit Warning Banner */}
                {isFreeLimitReached && (
                    <Banner
                        tone="warning"
                        title={`Free Plan Reservation Limit Reached (${bookings.length} / 10 Active Deposits)`}
                        action={{
                            content: 'Upgrade to Premium ($5/mo)',
                            onAction: () => {
                                setSelectedTab(2);
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                            },
                        }}
                    >
                        <p>
                            You have reached or exceeded the <strong>10 active deposit reservations limit</strong> on the Free Plan (currently <strong>{bookings.length}</strong> deposit reservations). Storefront deposit reservations may be paused until you upgrade. Upgrade to the <strong>Premium Plan ($5/month)</strong> for unlimited deposit reservations, balance payment links, and 24/7 priority support.
                        </p>
                    </Banner>
                )}

                {/* TAB 0: OVERVIEW */}
                {selectedTab === 0 && (
                    <BlockStack gap="400">
                        {/* Summary Metrics */}
                        <Grid>
                            <Grid.Cell columnSpan={{ xs: 6, sm: 4, md: 4, lg: 4, xl: 4 }}>
                                <Card>
                                    <BlockStack gap="200">
                                        <Text variant="headingSm" as="h3" tone="subdued">
                                            Revenue Recovered
                                        </Text>
                                        <Text variant="headingLg" as="p">
                                            ${Number(revenueRecovered).toFixed(2)}
                                        </Text>
                                        <Text variant="bodyXs" tone="success">
                                            From paid balance invoices
                                        </Text>
                                    </BlockStack>
                                </Card>
                            </Grid.Cell>

                            <Grid.Cell columnSpan={{ xs: 6, sm: 4, md: 4, lg: 4, xl: 4 }}>
                                <Card>
                                    <BlockStack gap="200">
                                        <Text variant="headingSm" as="h3" tone="subdued">
                                            Active Deposit Orders
                                        </Text>
                                        <Text variant="headingLg" as="p">
                                            {activeBookings}
                                        </Text>
                                        <Text variant="bodyXs" tone="attention">
                                            Awaiting remaining balance
                                        </Text>
                                    </BlockStack>
                                </Card>
                            </Grid.Cell>

                            <Grid.Cell columnSpan={{ xs: 6, sm: 4, md: 4, lg: 4, xl: 4 }}>
                                <Card>
                                    <BlockStack gap="200">
                                        <Text variant="headingSm" as="h3" tone="subdued">
                                            Expiring Soon (7 Days)
                                        </Text>
                                        <Text variant="headingLg" as="p">
                                            {expiringSoonCount}
                                        </Text>
                                        <Text variant="bodyXs" tone="critical">
                                            Requires reminder action
                                        </Text>
                                    </BlockStack>
                                </Card>
                            </Grid.Cell>
                        </Grid>

                        {/* Recent Deferred Orders Table */}
                        <Card>
                            <BlockStack gap="300">
                                <InlineStack align="space-between" blockAlign="center">
                                    <Text variant="headingMd" as="h2">
                                        Recent Deferred Orders
                                    </Text>
                                    <InlineStack gap="200">
                                        <Button icon={ExportIcon} onClick={handleExportCSV}>
                                            Export CSV
                                        </Button>
                                        <Button
                                            variant="tertiary"
                                            onClick={() => {
                                                setSelectedTab(1);
                                                window.scrollTo({ top: 0, behavior: 'smooth' });
                                                if (window.history && window.history.pushState) {
                                                    window.history.pushState({}, '', '/bookings');
                                                }
                                            }}
                                        >
                                            View All ({bookings.length})
                                        </Button>
                                    </InlineStack>
                                </InlineStack>
                                <Divider />

                                {bookings.length === 0 ? (
                                    <Box padding="400">
                                        <Text tone="subdued" alignment="center">
                                            No deposit orders received yet. When customers buy via deposit on your storefront, orders will appear here.
                                        </Text>
                                    </Box>
                                ) : (
                                    <IndexTable
                                        resourceName={{ singular: 'booking', plural: 'bookings' }}
                                        itemCount={Math.min(bookings.length, 5)}
                                        selectable={false}
                                        headings={[
                                            { title: 'Order' },
                                            { title: 'Customer' },
                                            { title: 'Product' },
                                            { title: 'Deposit Paid' },
                                            { title: 'Balance Due' },
                                            { title: 'Payment status' },
                                        ]}
                                    >
                                        {bookings.slice(0, 5).map((booking, index) => (
                                            <IndexTable.Row id={String(booking.id)} key={booking.id} position={index}>
                                                <IndexTable.Cell>
                                                    {getOrderAdminUrl(booking) ? (
                                                        <a
                                                            href={getOrderAdminUrl(booking)}
                                                            target="_top"
                                                            rel="noopener noreferrer"
                                                            style={{
                                                                color: '#005bd3',
                                                                fontWeight: '700',
                                                                textDecoration: 'none',
                                                                cursor: 'pointer',
                                                            }}
                                                            onMouseEnter={(e) => (e.currentTarget.style.textDecoration = 'underline')}
                                                            onMouseLeave={(e) => (e.currentTarget.style.textDecoration = 'none')}
                                                        >
                                                            {formatOrderNumber(booking)}
                                                        </a>
                                                    ) : (
                                                        <Text variant="bodyMd" fontWeight="bold">
                                                            {formatOrderNumber(booking)}
                                                        </Text>
                                                    )}
                                                </IndexTable.Cell>
                                                <IndexTable.Cell>
                                                    <BlockStack gap="050">
                                                        <Text variant="bodyMd" fontWeight="semibold">
                                                            {booking.email}
                                                        </Text>
                                                        <Text variant="bodyXs" tone="subdued">
                                                            {new Date(booking.created_at).toLocaleDateString()}
                                                        </Text>
                                                    </BlockStack>
                                                </IndexTable.Cell>
                                                <IndexTable.Cell>
                                                    <Text variant="bodyMd">{booking.product_title}</Text>
                                                </IndexTable.Cell>
                                                <IndexTable.Cell>
                                                    <Text variant="bodyMd" fontWeight="bold">
                                                        ${Number(booking.deposit_amount).toFixed(2)}
                                                    </Text>
                                                </IndexTable.Cell>
                                                <IndexTable.Cell>
                                                    <Text variant="bodyMd">
                                                        ${Number(booking.remaining_balance).toFixed(2)}
                                                    </Text>
                                                </IndexTable.Cell>
                                                <IndexTable.Cell>{renderPaymentStatusBadge(booking)}</IndexTable.Cell>
                                            </IndexTable.Row>
                                        ))}
                                    </IndexTable>
                                )}
                            </BlockStack>
                        </Card>
                    </BlockStack>
                )}

                {/* TAB 1: BOOKINGS LIST */}
                {selectedTab === 1 && (
                    <Card>
                        <BlockStack gap="400">
                            <InlineStack align="space-between" blockAlign="center">
                                <Text variant="headingMd" as="h2">
                                    All Deferred Orders & Bookings
                                </Text>
                                <Button icon={ExportIcon} onClick={handleExportCSV}>
                                    Export CSV
                                </Button>
                            </InlineStack>
                            <Divider />

                            {bookings.length === 0 ? (
                                <Box padding="600">
                                    <Text tone="subdued" alignment="center">
                                        No bookings found.
                                    </Text>
                                </Box>
                            ) : (
                                <IndexTable
                                    resourceName={{ singular: 'booking', plural: 'bookings' }}
                                    itemCount={bookings.length}
                                    selectable={false}
                                    headings={[
                                        { title: 'Order' },
                                        { title: 'Customer' },
                                        { title: 'Product' },
                                        { title: 'Deposit Paid' },
                                        { title: 'Balance Due' },
                                        { title: 'Payment status' },
                                    ]}
                                >
                                    {bookings.map((booking, index) => (
                                        <IndexTable.Row id={String(booking.id)} key={booking.id} position={index}>
                                            <IndexTable.Cell>
                                                {getOrderAdminUrl(booking) ? (
                                                    <a
                                                        href={getOrderAdminUrl(booking)}
                                                        target="_top"
                                                        rel="noopener noreferrer"
                                                        style={{
                                                            color: '#005bd3',
                                                            fontWeight: '700',
                                                            textDecoration: 'none',
                                                            cursor: 'pointer',
                                                        }}
                                                        onMouseEnter={(e) => (e.currentTarget.style.textDecoration = 'underline')}
                                                        onMouseLeave={(e) => (e.currentTarget.style.textDecoration = 'none')}
                                                    >
                                                        {formatOrderNumber(booking)}
                                                    </a>
                                                ) : (
                                                    <Text variant="bodyMd" fontWeight="bold">
                                                        {formatOrderNumber(booking)}
                                                    </Text>
                                                )}
                                            </IndexTable.Cell>
                                            <IndexTable.Cell>
                                                <BlockStack gap="050">
                                                    <Text variant="bodyMd" fontWeight="semibold">
                                                        {booking.email}
                                                    </Text>
                                                    <Text variant="bodyXs" tone="subdued">
                                                        {new Date(booking.created_at).toLocaleDateString()}
                                                    </Text>
                                                </BlockStack>
                                            </IndexTable.Cell>
                                            <IndexTable.Cell>{booking.product_title}</IndexTable.Cell>
                                            <IndexTable.Cell fontWeight="bold">
                                                ${Number(booking.deposit_amount).toFixed(2)}
                                            </IndexTable.Cell>
                                            <IndexTable.Cell>${Number(booking.remaining_balance).toFixed(2)}</IndexTable.Cell>
                                            <IndexTable.Cell>{renderPaymentStatusBadge(booking)}</IndexTable.Cell>
                                        </IndexTable.Row>
                                    ))}
                                </IndexTable>
                            )}
                        </BlockStack>
                    </Card>
                )}

                {/* TAB 2: PRICE PLAN */}
                {selectedTab === 2 && (
                    <BlockStack gap="400">
                        <BlockStack gap="100">
                            <Text variant="headingLg" as="h2">
                                Select Your Plan
                            </Text>
                            <Text tone="subdued">
                                Start for free or upgrade to unlock unlimited deposit reservations and priority support.
                            </Text>
                        </BlockStack>

                        <Grid>
                            {/* Free Plan */}
                            <Grid.Cell columnSpan={{ xs: 12, sm: 12, md: 6, lg: 6, xl: 6 }}>
                                <Box style={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
                                    <Card padding="500">
                                        <Box style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between', height: '100%', minHeight: '520px' }}>
                                            <BlockStack gap="400">
                                                <InlineStack align="space-between">
                                                    <Text variant="headingMd" as="h3">
                                                        Free Plan
                                                    </Text>
                                                    {!hasPlan && <Badge tone="info">Active Plan</Badge>}
                                                </InlineStack>

                                                <Text tone="subdued">
                                                    Perfect for getting started with deposit-based product reservations.
                                                </Text>

                                                <InlineStack align="start" blockAlign="baseline" gap="100">
                                                    <Text variant="heading2xl" as="span" fontWeight="bold">
                                                        $0
                                                    </Text>
                                                    <Text tone="subdued">/ month</Text>
                                                </InlineStack>

                                                <Divider />

                                                <BlockStack gap="250">
                                                    <InlineStack gap="200" align="start" blockAlign="start" wrap={false}>
                                                        <Box style={{ flexShrink: 0, marginTop: '2px' }}>
                                                            <Icon source={CheckIcon} tone="success" />
                                                        </Box>
                                                        <Text as="span">Up to 10 active deposit reservations</Text>
                                                    </InlineStack>
                                                    <InlineStack gap="200" align="start" blockAlign="start" wrap={false}>
                                                        <Box style={{ flexShrink: 0, marginTop: '2px' }}>
                                                            <Icon source={CheckIcon} tone="success" />
                                                        </Box>
                                                        <Text as="span">Buy Now Later widget on product pages</Text>
                                                    </InlineStack>
                                                    <InlineStack gap="200" align="start" blockAlign="start" wrap={false}>
                                                        <Box style={{ flexShrink: 0, marginTop: '2px' }}>
                                                            <Icon source={CheckIcon} tone="success" />
                                                        </Box>
                                                        <Text as="span">Configurable deposit percentage</Text>
                                                    </InlineStack>
                                                    <InlineStack gap="200" align="start" blockAlign="start" wrap={false}>
                                                        <Box style={{ flexShrink: 0, marginTop: '2px' }}>
                                                            <Icon source={CheckIcon} tone="success" />
                                                        </Box>
                                                        <Text as="span">Shopify Draft Order integration</Text>
                                                    </InlineStack>
                                                    <InlineStack gap="200" align="start" blockAlign="start" wrap={false}>
                                                        <Box style={{ flexShrink: 0, marginTop: '2px' }}>
                                                            <Icon source={CheckIcon} tone="success" />
                                                        </Box>
                                                        <Text as="span">Reservation dashboard & tracking</Text>
                                                    </InlineStack>
                                                    <InlineStack gap="200" align="start" blockAlign="start" wrap={false}>
                                                        <Box style={{ flexShrink: 0, marginTop: '2px' }}>
                                                            <Icon source={CheckIcon} tone="success" />
                                                        </Box>
                                                        <Text as="span">Email support</Text>
                                                    </InlineStack>
                                                </BlockStack>
                                            </BlockStack>

                                            <Box paddingTop="400">
                                                <Button disabled fullWidth>
                                                    {!hasPlan ? 'Current Plan' : 'Free Tier'}
                                                </Button>
                                            </Box>
                                        </Box>
                                    </Card>
                                </Box>
                            </Grid.Cell>

                            {/* Premium Plan */}
                            <Grid.Cell columnSpan={{ xs: 12, sm: 12, md: 6, lg: 6, xl: 6 }}>
                                <Box style={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
                                    <Card padding="500">
                                        <Box style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between', height: '100%', minHeight: '520px' }}>
                                            <BlockStack gap="400">
                                                <InlineStack align="space-between">
                                                    <Text variant="headingMd" as="h3">
                                                        Premium Plan
                                                    </Text>
                                                    <Badge tone="success">Most Popular</Badge>
                                                </InlineStack>

                                                <Text tone="subdued">
                                                    Unlimited deposit reservations with full controls and priority support.
                                                </Text>

                                                <InlineStack align="start" blockAlign="baseline" gap="100">
                                                    <Text variant="heading2xl" as="span" fontWeight="bold">
                                                        $5
                                                    </Text>
                                                    <Text tone="subdued">/ month</Text>
                                                </InlineStack>

                                                <Divider />

                                                <BlockStack gap="250">
                                                    <InlineStack gap="200" align="start" blockAlign="start" wrap={false}>
                                                        <Box style={{ flexShrink: 0, marginTop: '2px' }}>
                                                            <Icon source={CheckIcon} tone="success" />
                                                        </Box>
                                                        <Text as="span" fontWeight="bold">Unlimited deposit reservations & holds</Text>
                                                    </InlineStack>
                                                    <InlineStack gap="200" align="start" blockAlign="start" wrap={false}>
                                                        <Box style={{ flexShrink: 0, marginTop: '2px' }}>
                                                            <Icon source={CheckIcon} tone="success" />
                                                        </Box>
                                                        <Text as="span" fontWeight="bold">Custom deposit percentage per product or store-wide</Text>
                                                    </InlineStack>
                                                    <InlineStack gap="200" align="start" blockAlign="start" wrap={false}>
                                                        <Box style={{ flexShrink: 0, marginTop: '2px' }}>
                                                            <Icon source={CheckIcon} tone="success" />
                                                        </Box>
                                                        <Text as="span" fontWeight="bold">Balance payment links sent directly to customers</Text>
                                                    </InlineStack>
                                                    <InlineStack gap="200" align="start" blockAlign="start" wrap={false}>
                                                        <Box style={{ flexShrink: 0, marginTop: '2px' }}>
                                                            <Icon source={CheckIcon} tone="success" />
                                                        </Box>
                                                        <Text as="span" fontWeight="bold">Automated Draft Order Sync with Shopify admin</Text>
                                                    </InlineStack>
                                                    <InlineStack gap="200" align="start" blockAlign="start" wrap={false}>
                                                        <Box style={{ flexShrink: 0, marginTop: '2px' }}>
                                                            <Icon source={CheckIcon} tone="success" />
                                                        </Box>
                                                        <Text as="span" fontWeight="bold">Fulfillment Hold — auto-holds order until balance is paid</Text>
                                                    </InlineStack>
                                                    <InlineStack gap="200" align="start" blockAlign="start" wrap={false}>
                                                        <Box style={{ flexShrink: 0, marginTop: '2px' }}>
                                                            <Icon source={CheckIcon} tone="success" />
                                                        </Box>
                                                        <Text as="span" fontWeight="bold">Configurable hold expiry — set how long reservations last</Text>
                                                    </InlineStack>
                                                    <InlineStack gap="200" align="start" blockAlign="start" wrap={false}>
                                                        <Box style={{ flexShrink: 0, marginTop: '2px' }}>
                                                            <Icon source={CheckIcon} tone="success" />
                                                        </Box>
                                                        <Text as="span" fontWeight="bold">Theme App Extension — no code edits required</Text>
                                                    </InlineStack>
                                                    <InlineStack gap="200" align="start" blockAlign="start" wrap={false}>
                                                        <Box style={{ flexShrink: 0, marginTop: '2px' }}>
                                                            <Icon source={CheckIcon} tone="success" />
                                                        </Box>
                                                        <Text as="span" fontWeight="bold">Priority Support — 24/7 Email & Live Chat</Text>
                                                    </InlineStack>
                                                </BlockStack>
                                            </BlockStack>

                                            <Box paddingTop="400">
                                                {hasPlan ? (
                                                    <Button disabled fullWidth>Current Plan</Button>
                                                ) : (
                                                    <Button
                                                        variant="primary"
                                                        fullWidth
                                                        onClick={() => {
                                                            const urlParams = new URLSearchParams(window.location.search);
                                                            const shop = urlParams.get('shop') || shopName;
                                                            window.location.href = `/billing?plan=1&shop=${encodeURIComponent(shop)}`;
                                                        }}
                                                    >
                                                        Upgrade to Premium ($5/mo)
                                                    </Button>
                                                )}
                                            </Box>
                                        </Box>
                                    </Card>
                                </Box>
                            </Grid.Cell>
                        </Grid>
                    </BlockStack>
                )}

                {/* TAB 3: APP SETTINGS */}
                {selectedTab === 3 && (
                    <Card title="App Settings">
                        <form onSubmit={handleSaveSettings}>
                            <FormLayout>
                                <Text variant="headingMd" as="h2">
                                    Deferred Purchase & Deposit Configuration
                                </Text>
                                <Text variant="bodySm" tone="subdued">
                                    Configure deposit percentage, hold duration, and product targeting rules. Changes automatically sync to Shopify Selling Plan Groups.
                                </Text>
                                <Divider />

                                <Grid>
                                    <Grid.Cell columnSpan={{ xs: 6, sm: 6, md: 6, lg: 6, xl: 6 }}>
                                        <TextField
                                            label="Deposit Percentage (%)"
                                            type="number"
                                            value={String(depositPercentage)}
                                            onChange={(val) => setDepositPercentage(Number(val))}
                                            helpText="Percentage customer pays upfront at checkout (e.g. 10%)."
                                            min={1}
                                            max={100}
                                            autoComplete="off"
                                        />
                                    </Grid.Cell>

                                    <Grid.Cell columnSpan={{ xs: 6, sm: 6, md: 6, lg: 6, xl: 6 }}>
                                        <TextField
                                            label="Reservation Hold Duration (Days)"
                                            type="number"
                                            value={String(holdDurationDays)}
                                            onChange={(val) => setHoldDurationDays(Number(val))}
                                            helpText="Number of days before unpaid balance reservation expires."
                                            min={1}
                                            max={365}
                                            autoComplete="off"
                                        />
                                    </Grid.Cell>
                                </Grid>

                                <Divider />

                                <Text variant="headingMd" as="h2">
                                    Product Targeting & Visibility Rules
                                </Text>

                                <Select
                                    label="Widget Visibility"
                                    options={[
                                        { label: 'Show on All Products', value: 'all' },
                                        { label: 'Show only on Selected Products', value: 'specific' },
                                        { label: 'Show on All Products, except selected', value: 'exclude' },
                                    ]}
                                    value={productTargetingType}
                                    onChange={(value) => setProductTargetingType(value)}
                                />

                                {(productTargetingType === 'specific' || productTargetingType === 'exclude') && (
                                    <BlockStack gap="300">
                                        <TextField
                                            label="Search Products to Add"
                                            value={searchQuery}
                                            onChange={(val) => {
                                                setSearchQuery(val);
                                                handleProductSearch(val);
                                            }}
                                            placeholder="Type product title..."
                                            prefix={<Icon source={SearchIcon} />}
                                            autoComplete="off"
                                        />

                                        {isSearching && (
                                            <InlineStack gap="200" align="center">
                                                <Spinner size="small" />
                                                <Text tone="subdued">Searching products...</Text>
                                            </InlineStack>
                                        )}

                                        {searchResults.length > 0 && (
                                            <Card padding="200">
                                                <BlockStack gap="100">
                                                    {searchResults.map((prod) => {
                                                        const isAlreadySelected = selectedProductsList.some(
                                                            (p) => String(p.id) === String(prod.id)
                                                        );
                                                        return (
                                                            <InlineStack
                                                                key={prod.id}
                                                                align="space-between"
                                                                blockAlign="center"
                                                            >
                                                                <Text>{prod.title}</Text>
                                                                <Button
                                                                    size="micro"
                                                                    disabled={isAlreadySelected}
                                                                    onClick={() => handleAddProduct(prod)}
                                                                >
                                                                    {isAlreadySelected ? 'Added' : 'Add'}
                                                                </Button>
                                                            </InlineStack>
                                                        );
                                                    })}
                                                </BlockStack>
                                            </Card>
                                        )}

                                        {selectedProductsList.length > 0 && (
                                            <BlockStack gap="200">
                                                <Text fontWeight="semibold">Selected Products:</Text>
                                                <InlineStack gap="200" wrap>
                                                    {selectedProductsList.map((prod) => (
                                                        <Badge
                                                            key={prod.id}
                                                            onDismiss={() => handleRemoveProduct(prod.id)}
                                                        >
                                                            {prod.title}
                                                        </Badge>
                                                    ))}
                                                </InlineStack>
                                            </BlockStack>
                                        )}
                                    </BlockStack>
                                )}

                                <Box paddingBlockStart="400">
                                    <Button variant="primary" submit loading={isSaving}>
                                        Save & Sync Settings
                                    </Button>
                                </Box>
                            </FormLayout>
                        </form>
                    </Card>
                )}

                {/* TAB 4: SUPPORT & HELP */}
                {selectedTab === 4 && (
                    <BlockStack gap="400">
                        {/* Quick 2-Step Installation Banner */}
                        <Banner tone="info" title="Quick 2-Step Installation">
                            <p>No coding required! Simply activate the app block in your Shopify Theme Editor.</p>
                        </Banner>

                        {/* Step 1 Card */}
                        <Card padding="500">
                            <BlockStack gap="300">
                                <Text variant="headingMd" as="h3">
                                    Step 1: Open Theme Editor
                                </Text>
                                <Text tone="subdued">
                                    Click the button below to navigate to your live Shopify Online Store Theme Editor.
                                </Text>
                                <Box paddingTop="200">
                                    <Button
                                        variant="primary"
                                        onClick={() => {
                                            const myshopifyDomain = shopName ? (shopName.includes('.myshopify.com') ? shopName : `${shopName}.myshopify.com`) : '';
                                            const themeEditorUrl = myshopifyDomain
                                                ? `https://${myshopifyDomain}/admin/themes/current/editor?template=product`
                                                : '/admin/themes/current/editor?template=product';
                                            window.open(themeEditorUrl, '_blank');
                                        }}
                                    >
                                        Open Shopify Theme Editor ↗
                                    </Button>
                                </Box>
                            </BlockStack>
                        </Card>

                        {/* Step 2 Card */}
                        <Card padding="500">
                            <BlockStack gap="300">
                                <Text variant="headingMd" as="h3">
                                    Step 2: Add App Block to Product Template
                                </Text>
                                <BlockStack gap="200">
                                    <Text variant="bodyMd">
                                        1. In the Theme Editor dropdown, select <strong>Products → Default product</strong>.
                                    </Text>
                                    <Text variant="bodyMd">
                                        2. In the left sidebar, click <strong>Add block</strong> under Product Information.
                                    </Text>
                                    <Text variant="bodyMd">
                                        3. Select <strong>Buy Now Later Widget</strong> under the Apps tab.
                                    </Text>
                                    <Text variant="bodyMd">
                                        4. Click <strong>Save</strong> in the top right corner.
                                    </Text>
                                </BlockStack>
                            </BlockStack>
                        </Card>

                        {/* Feedback & Complaint Form Card */}
                        <Card padding="500">
                            <form onSubmit={handleFeedbackSubmit}>
                                <FormLayout>
                                    <BlockStack gap="200">
                                        <Text variant="headingMd" as="h2">
                                            📩 Feedback & Complaint Form
                                        </Text>
                                        <Text tone="subdued">
                                            Have a feature suggestion, found a bug, or want to register a complaint? Let us know directly. We value your input and respond to support messages within 24 hours.
                                        </Text>
                                    </BlockStack>

                                    {feedbackSuccess && (
                                        <Banner tone="success" onDismiss={() => setFeedbackSuccess(false)}>
                                            <p>Thank you! Your feedback has been sent successfully. We will get back to you within 24 hours.</p>
                                        </Banner>
                                    )}

                                    {feedbackError && (
                                        <Banner tone="critical" onDismiss={() => setFeedbackError('')}>
                                            <p>{feedbackError}</p>
                                        </Banner>
                                    )}

                                    <Grid>
                                        <Grid.Cell columnSpan={{ xs: 6, sm: 6, md: 6, lg: 6, xl: 6 }}>
                                            <Select
                                                label="Feedback Type"
                                                options={[
                                                    { label: 'General Feedback', value: 'General Feedback' },
                                                    { label: 'Bug Report', value: 'Bug Report' },
                                                    { label: 'Feature Request', value: 'Feature Request' },
                                                    { label: 'Complaint', value: 'Complaint' },
                                                ]}
                                                value={feedbackType}
                                                onChange={(val) => setFeedbackType(val)}
                                            />
                                        </Grid.Cell>
                                        <Grid.Cell columnSpan={{ xs: 6, sm: 6, md: 6, lg: 6, xl: 6 }}>
                                            <TextField
                                                label="Contact Email"
                                                type="email"
                                                value={feedbackContact}
                                                onChange={(val) => setFeedbackContact(val)}
                                                autoComplete="email"
                                            />
                                        </Grid.Cell>
                                    </Grid>

                                    <TextField
                                        label="Subject"
                                        value={feedbackSubject}
                                        onChange={(val) => setFeedbackSubject(val)}
                                        placeholder="What is this about?"
                                        autoComplete="off"
                                    />

                                    <TextField
                                        label="Message"
                                        value={feedbackMessage}
                                        onChange={(val) => setFeedbackMessage(val)}
                                        placeholder="Detail your feedback, suggestion or complaint..."
                                        multiline={4}
                                        autoComplete="off"
                                    />

                                    <Box paddingTop="200">
                                        <Button variant="primary" submit loading={isSubmittingFeedback}>
                                            Submit Feedback
                                        </Button>
                                    </Box>
                                </FormLayout>
                            </form>
                        </Card>

                        {/* Direct Email Support Info */}
                        <Card padding="500">
                            <BlockStack gap="300">
                                <Text variant="headingMd" as="h2">
                                    Need Direct Assistance?
                                </Text>
                                <Divider />
                                <Text variant="bodyMd">
                                    Our support team is ready to help you with setup or custom theme integrations.
                                </Text>
                                <Text variant="bodyMd" fontWeight="bold">
                                    Email Support: buynowlater@cannyapps.com
                                </Text>
                            </BlockStack>
                        </Card>
                    </BlockStack>
                )}
            </BlockStack>
        </Page>
    );
}
