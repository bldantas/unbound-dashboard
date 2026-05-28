/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
import type { BaseHttpRequest } from './core/BaseHttpRequest';
import type { OpenAPIConfig } from './core/OpenAPI';
import { FetchHttpRequest } from './core/FetchHttpRequest';
import { AlertsService } from './services/AlertsService';
import { AnalyticsService } from './services/AnalyticsService';
import { ApiTokensService } from './services/ApiTokensService';
import { ApprovalsService } from './services/ApprovalsService';
import { AuditService } from './services/AuditService';
import { AuthService } from './services/AuthService';
import { BackupOffsiteService } from './services/BackupOffsiteService';
import { BlocklistService } from './services/BlocklistService';
import { ClusterService } from './services/ClusterService';
import { ComplianceService } from './services/ComplianceService';
import { DnsSecurityService } from './services/DnsSecurityService';
import { DohInboundService } from './services/DohInboundService';
import { ExportsService } from './services/ExportsService';
import { ExternalHealthService } from './services/ExternalHealthService';
import { GeoBlockingService } from './services/GeoBlockingService';
import { GeoipService } from './services/GeoipService';
import { GrafanaService } from './services/GrafanaService';
import { HaService } from './services/HaService';
import { HealthService } from './services/HealthService';
import { HistoryService } from './services/HistoryService';
import { HostService } from './services/HostService';
import { HostsService } from './services/HostsService';
import { NotificationsService } from './services/NotificationsService';
import { ObservabilityService } from './services/ObservabilityService';
import { OidcService } from './services/OidcService';
import { OrganizationsService } from './services/OrganizationsService';
import { PoliciesService } from './services/PoliciesService';
import { RateLimitsService } from './services/RateLimitsService';
import { SecretsStoreService } from './services/SecretsStoreService';
import { StatsService } from './services/StatsService';
import { ThreatsService } from './services/ThreatsService';
import { UnboundService } from './services/UnboundService';
import { UpdatesService } from './services/UpdatesService';
import { UsersService } from './services/UsersService';
import { WebhooksService } from './services/WebhooksService';
type HttpRequestConstructor = new (config: OpenAPIConfig) => BaseHttpRequest;
export class UnboundDashboardClient {
    public readonly alerts: AlertsService;
    public readonly analytics: AnalyticsService;
    public readonly apiTokens: ApiTokensService;
    public readonly approvals: ApprovalsService;
    public readonly audit: AuditService;
    public readonly auth: AuthService;
    public readonly backupOffsite: BackupOffsiteService;
    public readonly blocklist: BlocklistService;
    public readonly cluster: ClusterService;
    public readonly compliance: ComplianceService;
    public readonly dnsSecurity: DnsSecurityService;
    public readonly dohInbound: DohInboundService;
    public readonly exports: ExportsService;
    public readonly externalHealth: ExternalHealthService;
    public readonly geoBlocking: GeoBlockingService;
    public readonly geoip: GeoipService;
    public readonly grafana: GrafanaService;
    public readonly ha: HaService;
    public readonly health: HealthService;
    public readonly history: HistoryService;
    public readonly host: HostService;
    public readonly hosts: HostsService;
    public readonly notifications: NotificationsService;
    public readonly observability: ObservabilityService;
    public readonly oidc: OidcService;
    public readonly organizations: OrganizationsService;
    public readonly policies: PoliciesService;
    public readonly rateLimits: RateLimitsService;
    public readonly secretsStore: SecretsStoreService;
    public readonly stats: StatsService;
    public readonly threats: ThreatsService;
    public readonly unbound: UnboundService;
    public readonly updates: UpdatesService;
    public readonly users: UsersService;
    public readonly webhooks: WebhooksService;
    public readonly request: BaseHttpRequest;
    constructor(config?: Partial<OpenAPIConfig>, HttpRequest: HttpRequestConstructor = FetchHttpRequest) {
        this.request = new HttpRequest({
            BASE: config?.BASE ?? '',
            VERSION: config?.VERSION ?? '0.1.0',
            WITH_CREDENTIALS: config?.WITH_CREDENTIALS ?? false,
            CREDENTIALS: config?.CREDENTIALS ?? 'include',
            TOKEN: config?.TOKEN,
            USERNAME: config?.USERNAME,
            PASSWORD: config?.PASSWORD,
            HEADERS: config?.HEADERS,
            ENCODE_PATH: config?.ENCODE_PATH,
        });
        this.alerts = new AlertsService(this.request);
        this.analytics = new AnalyticsService(this.request);
        this.apiTokens = new ApiTokensService(this.request);
        this.approvals = new ApprovalsService(this.request);
        this.audit = new AuditService(this.request);
        this.auth = new AuthService(this.request);
        this.backupOffsite = new BackupOffsiteService(this.request);
        this.blocklist = new BlocklistService(this.request);
        this.cluster = new ClusterService(this.request);
        this.compliance = new ComplianceService(this.request);
        this.dnsSecurity = new DnsSecurityService(this.request);
        this.dohInbound = new DohInboundService(this.request);
        this.exports = new ExportsService(this.request);
        this.externalHealth = new ExternalHealthService(this.request);
        this.geoBlocking = new GeoBlockingService(this.request);
        this.geoip = new GeoipService(this.request);
        this.grafana = new GrafanaService(this.request);
        this.ha = new HaService(this.request);
        this.health = new HealthService(this.request);
        this.history = new HistoryService(this.request);
        this.host = new HostService(this.request);
        this.hosts = new HostsService(this.request);
        this.notifications = new NotificationsService(this.request);
        this.observability = new ObservabilityService(this.request);
        this.oidc = new OidcService(this.request);
        this.organizations = new OrganizationsService(this.request);
        this.policies = new PoliciesService(this.request);
        this.rateLimits = new RateLimitsService(this.request);
        this.secretsStore = new SecretsStoreService(this.request);
        this.stats = new StatsService(this.request);
        this.threats = new ThreatsService(this.request);
        this.unbound = new UnboundService(this.request);
        this.updates = new UpdatesService(this.request);
        this.users = new UsersService(this.request);
        this.webhooks = new WebhooksService(this.request);
    }
}

