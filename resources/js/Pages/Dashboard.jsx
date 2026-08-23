import React, { useState, useEffect } from 'react';
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

    // Booking action loading state
    const [actionLoading, setActionLoading] = useState({});

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

    const renderStatusBadge = (status) => {
        switch (status) {
            case 'completed':
                return <Badge tone="success">Completed</Badge>;
            case 'deposit_paid':
                return <Badge tone="attention">Deposit Paid</Badge>;
            case 'pending':
                return <Badge tone="warning">Pending</Badge>;
            case 'expired':
                return <Badge tone="critical">Expired</Badge>;
            default:
                return <Badge tone="subdued">{status}</Badge>;
        }
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

                {/* TAB 0: OVERVIEW */}
                {selectedTab === 0 && (
                    <BlockStack gap="400">
                        {/* Summary Metrics */}
                        <Grid>
                            <Grid.Cell columnSpan={{ xs: 6, sm: 3, md: 3, lg: 3, xl: 3 }}>
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

                            <Grid.Cell columnSpan={{ xs: 6, sm: 3, md: 3, lg: 3, xl: 3 }}>
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

                            <Grid.Cell columnSpan={{ xs: 6, sm: 3, md: 3, lg: 3, xl: 3 }}>
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

                            <Grid.Cell columnSpan={{ xs: 6, sm: 3, md: 3, lg: 3, xl: 3 }}>
                                <Card>
                                    <BlockStack gap="200">
                                        <Text variant="headingSm" as="h3" tone="subdued">
                                            Price Drop Subscribers
                                        </Text>
                                        <Text variant="headingLg" as="p">
                                            {alertSubscribersCount}
                                        </Text>
                                        <Text variant="bodyXs" tone="subdued">
                                            Interested customers
                                        </Text>
                                    </BlockStack>
                                </Card>
                            </Grid.Cell>
                        </Grid>

                        {/* Recent Deferred Orders Table */}
                        <Card>
                            <BlockStack gap="300">
                                <InlineStack align="space-between">
                                    <Text variant="headingMd" as="h2">
                                        Recent Deferred Orders
                                    </Text>
                                    <Button variant="tertiary" onClick={() => setSelectedTab(1)}>
                                        View All ({bookings.length})
                                    </Button>
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
                                            { title: 'Status' },
                                            { title: 'Action' },
                                        ]}
                                    >
                                        {bookings.slice(0, 5).map((booking, index) => (
                                            <IndexTable.Row id={String(booking.id)} key={booking.id} position={index}>
                                                <IndexTable.Cell>
                                                    <Text variant="bodyMd" fontWeight="bold">
                                                        {formatOrderNumber(booking)}
                                                    </Text>
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
                                                <IndexTable.Cell>{renderStatusBadge(booking.status)}</IndexTable.Cell>
                                                <IndexTable.Cell>
                                                    {booking.status === 'deposit_paid' && (
                                                        <Button
                                                            size="micro"
                                                            variant="primary"
                                                            loading={actionLoading[booking.id]}
                                                            onClick={() => handleSendAction(booking.id, 'reminder')}
                                                        >
                                                            Send Balance Link
                                                        </Button>
                                                    )}
                                                </IndexTable.Cell>
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
                            <InlineStack align="space-between">
                                <Text variant="headingMd" as="h2">
                                    All Deferred Orders & Bookings
                                </Text>
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
                                        { title: 'Customer Email' },
                                        { title: 'Product' },
                                        { title: 'Product Price' },
                                        { title: 'Deposit Paid' },
                                        { title: 'Remaining Balance' },
                                        { title: 'Status' },
                                        { title: 'Actions' },
                                    ]}
                                >
                                    {bookings.map((booking, index) => (
                                        <IndexTable.Row id={String(booking.id)} key={booking.id} position={index}>
                                            <IndexTable.Cell>
                                                <Text variant="bodyMd" fontWeight="bold">
                                                    {formatOrderNumber(booking)}
                                                </Text>
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
                                            <IndexTable.Cell>${Number(booking.product_price).toFixed(2)}</IndexTable.Cell>
                                            <IndexTable.Cell fontWeight="bold">
                                                ${Number(booking.deposit_amount).toFixed(2)}
                                            </IndexTable.Cell>
                                            <IndexTable.Cell>${Number(booking.remaining_balance).toFixed(2)}</IndexTable.Cell>
                                            <IndexTable.Cell>{renderStatusBadge(booking.status)}</IndexTable.Cell>
                                            <IndexTable.Cell>
                                                <InlineStack gap="200">
                                                    {booking.status === 'deposit_paid' && (
                                                        <Button
                                                            size="micro"
                                                            variant="primary"
                                                            loading={actionLoading[booking.id]}
                                                            onClick={() => handleSendAction(booking.id, 'reminder')}
                                                        >
                                                            Send Balance Invoice
                                                        </Button>
                                                    )}
                                                </InlineStack>
                                            </IndexTable.Cell>
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
                            <Grid.Cell columnSpan={{ xs: 6, sm: 6, md: 6, lg: 6, xl: 6 }}>
                                <Card padding="500">
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
                                            <InlineStack gap="200" blockAlign="center">
                                                <Icon source={CheckIcon} tone="success" />
                                                <Text>Up to 10 active deposit reservations</Text>
                                            </InlineStack>
                                            <InlineStack gap="200" blockAlign="center">
                                                <Icon source={CheckIcon} tone="success" />
                                                <Text>Buy Now Later widget on product pages</Text>
                                            </InlineStack>
                                            <InlineStack gap="200" blockAlign="center">
                                                <Icon source={CheckIcon} tone="success" />
                                                <Text>Configurable deposit percentage</Text>
                                            </InlineStack>
                                            <InlineStack gap="200" blockAlign="center">
                                                <Icon source={CheckIcon} tone="success" />
                                                <Text>Shopify Draft Order integration</Text>
                                            </InlineStack>
                                            <InlineStack gap="200" blockAlign="center">
                                                <Icon source={CheckIcon} tone="success" />
                                                <Text>Reservation dashboard & tracking</Text>
                                            </InlineStack>
                                            <InlineStack gap="200" blockAlign="center">
                                                <Icon source={CheckIcon} tone="success" />
                                                <Text>Email support</Text>
                                            </InlineStack>
                                        </BlockStack>

                                        <Box paddingTop="400">
                                            <Button disabled fullWidth>
                                                {!hasPlan ? 'Current Plan' : 'Free Tier'}
                                            </Button>
                                        </Box>
                                    </BlockStack>
                                </Card>
                            </Grid.Cell>

                            {/* Premium Plan */}
                            <Grid.Cell columnSpan={{ xs: 6, sm: 6, md: 6, lg: 6, xl: 6 }}>
                                <Card padding="500">
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
                                            <InlineStack gap="200" blockAlign="center">
                                                <Icon source={CheckIcon} tone="success" />
                                                <Text fontWeight="bold">Unlimited deposit reservations & holds</Text>
                                            </InlineStack>
                                            <InlineStack gap="200" blockAlign="center">
                                                <Icon source={CheckIcon} tone="success" />
                                                <Text fontWeight="bold">Custom deposit percentage per product or store-wide</Text>
                                            </InlineStack>
                                            <InlineStack gap="200" blockAlign="center">
                                                <Icon source={CheckIcon} tone="success" />
                                                <Text fontWeight="bold">Balance payment links sent directly to customers</Text>
                                            </InlineStack>
                                            <InlineStack gap="200" blockAlign="center">
                                                <Icon source={CheckIcon} tone="success" />
                                                <Text fontWeight="bold">Automated Draft Order Sync with Shopify admin</Text>
                                            </InlineStack>
                                            <InlineStack gap="200" blockAlign="center">
                                                <Icon source={CheckIcon} tone="success" />
                                                <Text fontWeight="bold">Fulfillment Hold — auto-holds order until balance is paid</Text>
                                            </InlineStack>
                                            <InlineStack gap="200" blockAlign="center">
                                                <Icon source={CheckIcon} tone="success" />
                                                <Text fontWeight="bold">Configurable hold expiry — set how long reservations last</Text>
                                            </InlineStack>
                                            <InlineStack gap="200" blockAlign="center">
                                                <Icon source={CheckIcon} tone="success" />
                                                <Text fontWeight="bold">Theme App Extension — no code edits required</Text>
                                            </InlineStack>
                                            <InlineStack gap="200" blockAlign="center">
                                                <Icon source={CheckIcon} tone="success" />
                                                <Text fontWeight="bold">Priority Support — 24/7 Email & Live Chat</Text>
                                            </InlineStack>
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
                                    </BlockStack>
                                </Card>
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
                        <Card title="How It Works">
                            <BlockStack gap="300">
                                <Text variant="headingMd" as="h2">
                                    How Buy Now Later Works
                                </Text>
                                <Divider />
                                <Text variant="bodyMd">
                                    1. <strong>Storefront Widget:</strong> When enabled, customers see the Buy Now Later option on targeted product pages.
                                </Text>
                                <Text variant="bodyMd">
                                    2. <strong>Shopify Selling Plan Checkout:</strong> Customers pay the configured deposit (e.g. 10%) directly via native Shopify checkout.
                                </Text>
                                <Text variant="bodyMd">
                                    3. <strong>Automatic Tracking & Invoicing:</strong> Deposit orders appear in your Dashboard. You can send automated balance invoices when ready to fulfill.
                                </Text>
                            </BlockStack>
                        </Card>

                        <Card title="Support & Contact">
                            <BlockStack gap="300">
                                <Text variant="headingMd" as="h2">
                                    Need Assistance?
                                </Text>
                                <Divider />
                                <Text variant="bodyMd">
                                    Our support team is ready to help you with setup or custom integrations.
                                </Text>
                                <Text variant="bodyMd" fontWeight="bold">
                                    Email Support: support@cannyapps.com
                                </Text>
                            </BlockStack>
                        </Card>
                    </BlockStack>
                )}
            </BlockStack>
        </Page>
    );
}
