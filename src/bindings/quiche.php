<?php
namespace Amp\Quic\Bindings;
use FFI;
use Amp\Quic\Bindings\double;
interface iQuiche {}
interface iQuiche_ptr {}
/**
 * @property string_ptr $tzname
 * @property int $getdate_err
 * @property int $timezone
 * @property int $daylight
 * @property struct_in6_addr $in6addr_any
 * @property struct_in6_addr $in6addr_loopback
 * @property struct_in6_addr $in6addr_nodelocal_allnodes
 * @property struct_in6_addr $in6addr_linklocal_allnodes
 * @property struct_in6_addr $in6addr_linklocal_allrouters
 * @property struct_in6_addr $in6addr_linklocal_allv2routers
 */
class Quiche {
    const QUICHE_PROTOCOL_VERSION = 0x00000001;
    const QUICHE_MAX_CONN_ID_LEN = 20;
    const QUICHE_ERR_DONE = ((-1)) + 0 /* enum quiche_error */;
    const QUICHE_ERR_BUFFER_TOO_SHORT = ((-2)) + 0 /* enum quiche_error */;
    const QUICHE_ERR_INVALID_STATE = ((-6)) + 0 /* enum quiche_error */;
    const QUICHE_ERR_INVALID_STREAM_STATE = ((-7)) + 0 /* enum quiche_error */;
    const QUICHE_ERR_STREAM_STOPPED = ((-15)) + 0 /* enum quiche_error */;
    const QUICHE_ERR_STREAM_RESET = ((-16)) + 0 /* enum quiche_error */;
    public static function ffi(?string $pathToSoFile = QuicheFFI::SOFILE): QuicheFFI { return new QuicheFFI($pathToSoFile); }
    public static function sizeof($classOrObject): int { return QuicheFFI::sizeof($classOrObject); }
}
class QuicheFFI {
    const SOFILE = __DIR__ . '/libquiche.' . (PHP_OS_FAMILY === 'Darwin' ? 'dylib' : 'so');
    const TYPES_DEF = 'typedef int8_t int_least8_t;
typedef int16_t int_least16_t;
typedef int32_t int_least32_t;
typedef int64_t int_least64_t;
typedef uint8_t uint_least8_t;
typedef uint16_t uint_least16_t;
typedef uint32_t uint_least32_t;
typedef uint64_t uint_least64_t;
typedef int8_t int_fast8_t;
typedef int16_t int_fast16_t;
typedef int32_t int_fast32_t;
typedef int64_t int_fast64_t;
typedef uint8_t uint_fast8_t;
typedef uint16_t uint_fast16_t;
typedef uint32_t uint_fast32_t;
typedef uint64_t uint_fast64_t;
typedef signed char __int8_t;
typedef unsigned char __uint8_t;
typedef short __int16_t;
typedef unsigned short __uint16_t;
typedef int __int32_t;
typedef unsigned int __uint32_t;
typedef long long __int64_t;
typedef unsigned long long __uint64_t;
typedef long __darwin_intptr_t;
typedef unsigned int __darwin_natural_t;
typedef int __darwin_ct_rune_t;
typedef union {
  char __mbstate8[128];
  long long _mbstateL;
} __mbstate_t;
typedef __mbstate_t __darwin_mbstate_t;
typedef long int __darwin_ptrdiff_t;
typedef long unsigned int __darwin_size_t;
typedef __builtin_va_list __darwin_va_list;
typedef int __darwin_wchar_t;
typedef __darwin_wchar_t __darwin_rune_t;
typedef unsigned int __darwin_wint_t;
typedef unsigned long __darwin_clock_t;
typedef __uint32_t __darwin_socklen_t;
typedef long __darwin_ssize_t;
typedef long __darwin_time_t;
typedef __int64_t __darwin_blkcnt_t;
typedef __int32_t __darwin_blksize_t;
typedef __int32_t __darwin_dev_t;
typedef unsigned int __darwin_fsblkcnt_t;
typedef unsigned int __darwin_fsfilcnt_t;
typedef __uint32_t __darwin_gid_t;
typedef __uint32_t __darwin_id_t;
typedef __uint64_t __darwin_ino64_t;
typedef __darwin_ino64_t __darwin_ino_t;
typedef __darwin_natural_t __darwin_mach_port_name_t;
typedef __darwin_mach_port_name_t __darwin_mach_port_t;
typedef __uint16_t __darwin_mode_t;
typedef __int64_t __darwin_off_t;
typedef __int32_t __darwin_pid_t;
typedef __uint32_t __darwin_sigset_t;
typedef __int32_t __darwin_suseconds_t;
typedef __uint32_t __darwin_uid_t;
typedef __uint32_t __darwin_useconds_t;
typedef unsigned char __darwin_uuid_t[16];
typedef char __darwin_uuid_string_t[37];
struct __darwin_pthread_handler_rec {
  void (*__routine)(void *);
  void *__arg;
  struct __darwin_pthread_handler_rec *__next;
};
struct _opaque_pthread_attr_t {
  long __sig;
  char __opaque[56];
};
struct _opaque_pthread_cond_t {
  long __sig;
  char __opaque[40];
};
struct _opaque_pthread_condattr_t {
  long __sig;
  char __opaque[8];
};
struct _opaque_pthread_mutex_t {
  long __sig;
  char __opaque[56];
};
struct _opaque_pthread_mutexattr_t {
  long __sig;
  char __opaque[8];
};
struct _opaque_pthread_once_t {
  long __sig;
  char __opaque[8];
};
struct _opaque_pthread_rwlock_t {
  long __sig;
  char __opaque[192];
};
struct _opaque_pthread_rwlockattr_t {
  long __sig;
  char __opaque[16];
};
struct _opaque_pthread_t {
  long __sig;
  struct __darwin_pthread_handler_rec *__cleanup_stack;
  char __opaque[8176];
};
typedef struct _opaque_pthread_attr_t __darwin_pthread_attr_t;
typedef struct _opaque_pthread_cond_t __darwin_pthread_cond_t;
typedef struct _opaque_pthread_condattr_t __darwin_pthread_condattr_t;
typedef unsigned long __darwin_pthread_key_t;
typedef struct _opaque_pthread_mutex_t __darwin_pthread_mutex_t;
typedef struct _opaque_pthread_mutexattr_t __darwin_pthread_mutexattr_t;
typedef struct _opaque_pthread_once_t __darwin_pthread_once_t;
typedef struct _opaque_pthread_rwlock_t __darwin_pthread_rwlock_t;
typedef struct _opaque_pthread_rwlockattr_t __darwin_pthread_rwlockattr_t;
typedef struct _opaque_pthread_t *__darwin_pthread_t;
typedef unsigned char u_int8_t;
typedef unsigned short u_int16_t;
typedef unsigned int u_int32_t;
typedef unsigned long long u_int64_t;
typedef int64_t register_t;
typedef unsigned long uintptr_t;
typedef u_int64_t user_addr_t;
typedef u_int64_t user_size_t;
typedef int64_t user_ssize_t;
typedef int64_t user_long_t;
typedef u_int64_t user_ulong_t;
typedef int64_t user_time_t;
typedef int64_t user_off_t;
typedef u_int64_t syscall_arg_t;
typedef __darwin_intptr_t intptr_t;
typedef long int intmax_t;
typedef long unsigned int uintmax_t;
typedef int __darwin_nl_item;
typedef int __darwin_wctrans_t;
typedef __uint32_t __darwin_wctype_t;
typedef __darwin_ptrdiff_t ptrdiff_t;
typedef __darwin_size_t rsize_t;
typedef __darwin_wchar_t wchar_t;
typedef __darwin_wint_t wint_t;
typedef unsigned char u_char;
typedef unsigned short u_short;
typedef unsigned int u_int;
typedef unsigned long u_long;
typedef unsigned short ushort;
typedef unsigned int uint;
typedef u_int64_t u_quad_t;
typedef int64_t quad_t;
typedef quad_t *qaddr_t;
typedef char *caddr_t;
typedef int32_t daddr_t;
typedef __darwin_dev_t dev_t;
typedef u_int32_t fixpt_t;
typedef __darwin_blkcnt_t blkcnt_t;
typedef __darwin_blksize_t blksize_t;
typedef __darwin_gid_t gid_t;
typedef __uint32_t in_addr_t;
typedef __uint16_t in_port_t;
typedef __darwin_ino_t ino_t;
typedef __darwin_ino64_t ino64_t;
typedef __int32_t key_t;
typedef __darwin_mode_t mode_t;
typedef __uint16_t nlink_t;
typedef __darwin_id_t id_t;
typedef __darwin_pid_t pid_t;
typedef __darwin_off_t off_t;
typedef int32_t segsz_t;
typedef int32_t swblk_t;
typedef __darwin_uid_t uid_t;
typedef __darwin_clock_t clock_t;
typedef __darwin_ssize_t ssize_t;
typedef __darwin_time_t time_t;
typedef __darwin_useconds_t useconds_t;
typedef __darwin_suseconds_t suseconds_t;
typedef int errno_t;
typedef struct fd_set {
  __int32_t fds_bits[(((1024 % ((sizeof (__int32_t)) * 8)) == 0) ? (1024 / ((sizeof (__int32_t)) * 8)) : ((1024 / ((sizeof (__int32_t)) * 8)) + 1))];
} fd_set;
typedef __int32_t fd_mask;
typedef __darwin_pthread_attr_t pthread_attr_t;
typedef __darwin_pthread_cond_t pthread_cond_t;
typedef __darwin_pthread_condattr_t pthread_condattr_t;
typedef __darwin_pthread_mutex_t pthread_mutex_t;
typedef __darwin_pthread_mutexattr_t pthread_mutexattr_t;
typedef __darwin_pthread_once_t pthread_once_t;
typedef __darwin_pthread_rwlock_t pthread_rwlock_t;
typedef __darwin_pthread_rwlockattr_t pthread_rwlockattr_t;
typedef __darwin_pthread_t pthread_t;
typedef __darwin_pthread_key_t pthread_key_t;
typedef __darwin_fsblkcnt_t fsblkcnt_t;
typedef __darwin_fsfilcnt_t fsfilcnt_t;
typedef __uint8_t sa_family_t;
typedef __darwin_socklen_t socklen_t;
struct iovec {
  void *iov_base;
  size_t iov_len;
};
typedef __uint32_t sae_associd_t;
typedef __uint32_t sae_connid_t;
typedef struct sa_endpoints {
  unsigned int sae_srcif;
  struct sockaddr *sae_srcaddr;
  socklen_t sae_srcaddrlen;
  struct sockaddr *sae_dstaddr;
  socklen_t sae_dstaddrlen;
} sa_endpoints_t;
struct linger {
  int l_onoff;
  int l_linger;
};
struct accept_filter_arg {
  char af_name[16];
  char af_arg[(256 - 16)];
};
struct sockaddr {
  ' . (PHP_OS_FAMILY === 'Darwin' ? 'uint8_t sa_len;' : '') . '
  sa_family_t sa_family;
  char sa_data[14];
};
struct __sockaddr_header {
  ' . (PHP_OS_FAMILY === 'Darwin' ? 'uint8_t sa_len;' : '') . '
  sa_family_t sa_family;
};
struct sockproto {
  __uint16_t sp_family;
  __uint16_t sp_protocol;
};
struct sockaddr_storage {
  ' . (PHP_OS_FAMILY === 'Darwin' ? 'uint8_t ss_len;' : '') . '
  sa_family_t ss_family;
  char __ss_pad1[(((sizeof (__int64_t)) - (sizeof (__uint8_t))) - (sizeof (sa_family_t)))];
  __int64_t __ss_align;
  char __ss_pad2[((((128 - (sizeof (__uint8_t))) - (sizeof (sa_family_t))) - (((sizeof (__int64_t)) - (sizeof (__uint8_t))) - (sizeof (sa_family_t)))) - (sizeof (__int64_t)))];
};
struct msghdr {
  void *msg_name;
  socklen_t msg_namelen;
  struct iovec *msg_iov;
  int msg_iovlen;
  void *msg_control;
  socklen_t msg_controllen;
  int msg_flags;
};
struct cmsghdr {
  socklen_t cmsg_len;
  int cmsg_level;
  int cmsg_type;
};
struct cmsgcred {
  pid_t cmcred_pid;
  uid_t cmcred_uid;
  uid_t cmcred_euid;
  gid_t cmcred_gid;
  short cmcred_ngroups;
  gid_t cmcred_groups[16];
};
struct sf_hdtr {
  struct iovec *headers;
  int hdr_cnt;
  struct iovec *trailers;
  int trl_cnt;
};
struct timespec {
  __darwin_time_t tv_sec;
  long tv_nsec;
};
struct timeval {
  __darwin_time_t tv_sec;
  __darwin_suseconds_t tv_usec;
};
struct timeval64 {
  __int64_t tv_sec;
  __int64_t tv_usec;
};
struct itimerval {
  struct timeval it_interval;
  struct timeval it_value;
};
struct timezone {
  int tz_minuteswest;
  int tz_dsttime;
};
struct clockinfo {
  int hz;
  int tick;
  int tickadj;
  int stathz;
  int profhz;
};
struct tm {
  int tm_sec;
  int tm_min;
  int tm_hour;
  int tm_mday;
  int tm_mon;
  int tm_year;
  int tm_wday;
  int tm_yday;
  int tm_isdst;
  long tm_gmtoff;
  char *tm_zone;
};
typedef struct quiche_config quiche_config;
typedef struct quiche_conn quiche_conn;
typedef struct {
  struct sockaddr *from;
  socklen_t from_len;
  struct sockaddr *to;
  socklen_t to_len;
} quiche_recv_info;
typedef struct {
  struct sockaddr_storage from;
  socklen_t from_len;
  struct sockaddr_storage to;
  socklen_t to_len;
  struct timespec at;
} quiche_send_info;
typedef struct quiche_stream_iter quiche_stream_iter;
typedef struct quiche_connection_id_iter quiche_connection_id_iter;
typedef struct {
  size_t recv;
  size_t sent;
  size_t lost;
  size_t retrans;
  uint64_t sent_bytes;
  uint64_t recv_bytes;
  uint64_t lost_bytes;
  uint64_t stream_retrans_bytes;
  size_t paths_count;
  uint64_t reset_stream_count_local;
  uint64_t stopped_stream_count_local;
  uint64_t reset_stream_count_remote;
  uint64_t stopped_stream_count_remote;
} quiche_stats;
typedef struct {
  uint64_t peer_max_idle_timeout;
  uint64_t peer_max_udp_payload_size;
  uint64_t peer_initial_max_data;
  uint64_t peer_initial_max_stream_data_bidi_local;
  uint64_t peer_initial_max_stream_data_bidi_remote;
  uint64_t peer_initial_max_stream_data_uni;
  uint64_t peer_initial_max_streams_bidi;
  uint64_t peer_initial_max_streams_uni;
  uint64_t peer_ack_delay_exponent;
  uint64_t peer_max_ack_delay;
  _Bool peer_disable_active_migration;
  uint64_t peer_active_conn_id_limit;
  ssize_t peer_max_datagram_frame_size;
} quiche_transport_params;
typedef struct {
  struct sockaddr_storage local_addr;
  socklen_t local_addr_len;
  struct sockaddr_storage peer_addr;
  socklen_t peer_addr_len;
  ssize_t validation_state;
  _Bool active;
  size_t recv;
  size_t sent;
  size_t lost;
  size_t retrans;
  uint64_t rtt;
  size_t cwnd;
  uint64_t sent_bytes;
  uint64_t recv_bytes;
  uint64_t lost_bytes;
  uint64_t stream_retrans_bytes;
  size_t pmtu;
  uint64_t delivery_rate;
} quiche_path_stats;
typedef struct quiche_path_event quiche_path_event;
typedef struct quiche_socket_addr_iter quiche_socket_addr_iter;
typedef struct quiche_h3_config quiche_h3_config;
typedef struct quiche_h3_conn quiche_h3_conn;
typedef struct quiche_h3_event quiche_h3_event;
typedef struct {
  uint8_t *name;
  size_t name_len;
  uint8_t *value;
  size_t value_len;
} quiche_h3_header;
typedef struct {
  uint8_t urgency;
  _Bool incremental;
} quiche_h3_priority;
struct in_addr {
  in_addr_t s_addr;
};
struct sockaddr_in {
  ' . (PHP_OS_FAMILY === 'Darwin' ? 'uint8_t sin_len;' : '') . '
  sa_family_t sin_family;
  in_port_t sin_port;
  struct in_addr sin_addr;
  char sin_zero[8];
};
struct ip_opts {
  struct in_addr ip_dst;
  char ip_opts[40];
};
struct ip_mreq {
  struct in_addr imr_multiaddr;
  struct in_addr imr_interface;
};
struct ip_mreqn {
  struct in_addr imr_multiaddr;
  struct in_addr imr_address;
  int imr_ifindex;
};
struct ip_mreq_source {
  struct in_addr imr_multiaddr;
  struct in_addr imr_sourceaddr;
  struct in_addr imr_interface;
};
struct group_req {
  uint32_t gr_interface;
  struct sockaddr_storage gr_group;
};
struct group_source_req {
  uint32_t gsr_interface;
  struct sockaddr_storage gsr_group;
  struct sockaddr_storage gsr_source;
};
struct __msfilterreq {
  uint32_t msfr_ifindex;
  uint32_t msfr_fmode;
  uint32_t msfr_nsrcs;
  uint32_t __msfr_align;
  struct sockaddr_storage msfr_group;
  struct sockaddr_storage *msfr_srcs;
};
struct sockaddr;
struct in_pktinfo {
  unsigned int ipi_ifindex;
  struct in_addr ipi_spec_dst;
  struct in_addr ipi_addr;
};
typedef struct in6_addr {
  union {
    __uint8_t __u6_addr8[16];
    __uint16_t __u6_addr16[8];
    __uint32_t __u6_addr32[4];
  } __u6_addr;
} in6_addr_t;
struct sockaddr_in6 {
  ' . (PHP_OS_FAMILY === 'Darwin' ? 'uint8_t sin6_len;' : '') . '
  sa_family_t sin6_family;
  in_port_t sin6_port;
  __uint32_t sin6_flowinfo;
  struct in6_addr sin6_addr;
  __uint32_t sin6_scope_id;
};
struct ipv6_mreq {
  struct in6_addr ipv6mr_multiaddr;
  unsigned int ipv6mr_interface;
};
struct in6_pktinfo {
  struct in6_addr ipi6_addr;
  unsigned int ipi6_ifindex;
};
struct ip6_mtuinfo {
  struct sockaddr_in6 ip6m_addr;
  uint32_t ip6m_mtu;
};
struct cmsghdr;
struct sockaddr;
';
    const HEADER_DEF = self::TYPES_DEF . 'int quiche_enable_debug_logging(void (*cb)(char *line, void *argp), void *argp);
quiche_config *quiche_config_new(uint32_t version);
int quiche_config_load_cert_chain_from_pem_file(quiche_config *config, char *path);
int quiche_config_load_priv_key_from_pem_file(quiche_config *config, char *path);
int quiche_config_load_verify_locations_from_file(quiche_config *config, char *path);
int quiche_config_load_verify_locations_from_directory(quiche_config *config, char *path);
void quiche_config_verify_peer(quiche_config *config, _Bool v);
void quiche_config_log_keys(quiche_config *config);
int quiche_config_set_application_protos(quiche_config *config, uint8_t *protos, size_t protos_len);
void quiche_config_set_max_idle_timeout(quiche_config *config, uint64_t v);
void quiche_config_set_initial_max_data(quiche_config *config, uint64_t v);
void quiche_config_set_initial_max_stream_data_bidi_local(quiche_config *config, uint64_t v);
void quiche_config_set_initial_max_stream_data_bidi_remote(quiche_config *config, uint64_t v);
void quiche_config_set_initial_max_stream_data_uni(quiche_config *config, uint64_t v);
void quiche_config_set_initial_max_streams_bidi(quiche_config *config, uint64_t v);
void quiche_config_set_initial_max_streams_uni(quiche_config *config, uint64_t v);
void quiche_config_enable_dgram(quiche_config *config, _Bool enabled, size_t recv_queue_len, size_t send_queue_len);
void quiche_config_set_stateless_reset_token(quiche_config *config, uint8_t *v);
void quiche_config_free(quiche_config *config);
int quiche_header_info(uint8_t *buf, size_t buf_len, size_t dcil, uint32_t *version, uint8_t *type, uint8_t *scid, size_t *scid_len, uint8_t *dcid, size_t *dcid_len, uint8_t *token, size_t *token_len);
quiche_conn *quiche_accept(uint8_t *scid, size_t scid_len, uint8_t *odcid, size_t odcid_len, struct sockaddr *local, socklen_t local_len, struct sockaddr *peer, socklen_t peer_len, quiche_config *config);
quiche_conn *quiche_connect(char *server_name, uint8_t *scid, size_t scid_len, struct sockaddr *local, socklen_t local_len, struct sockaddr *peer, socklen_t peer_len, quiche_config *config);
ssize_t quiche_negotiate_version(uint8_t *scid, size_t scid_len, uint8_t *dcid, size_t dcid_len, uint8_t *out, size_t out_len);
ssize_t quiche_retry(uint8_t *scid, size_t scid_len, uint8_t *dcid, size_t dcid_len, uint8_t *new_scid, size_t new_scid_len, uint8_t *token, size_t token_len, uint32_t version, uint8_t *out, size_t out_len);
_Bool quiche_version_is_supported(uint32_t version);
_Bool quiche_conn_set_keylog_path(quiche_conn *conn, char *path);
ssize_t quiche_conn_recv(quiche_conn *conn, uint8_t *buf, size_t buf_len, quiche_recv_info *info);
ssize_t quiche_conn_send(quiche_conn *conn, uint8_t *out, size_t out_len, quiche_send_info *out_info);
ssize_t quiche_conn_stream_recv(quiche_conn *conn, uint64_t stream_id, uint8_t *out, size_t buf_len, _Bool *fin);
ssize_t quiche_conn_stream_send(quiche_conn *conn, uint64_t stream_id, uint8_t *buf, size_t buf_len, _Bool fin);
int quiche_conn_stream_priority(quiche_conn *conn, uint64_t stream_id, uint8_t urgency, _Bool incremental);
int quiche_conn_stream_shutdown(quiche_conn *conn, uint64_t stream_id, enum quiche_shutdown direction, uint64_t err);
int64_t quiche_conn_stream_readable_next(quiche_conn *conn);
int quiche_conn_stream_writable(quiche_conn *conn, uint64_t stream_id, size_t len);
int64_t quiche_conn_stream_writable_next(quiche_conn *conn);
uint64_t quiche_conn_timeout_as_nanos(quiche_conn *conn);
void quiche_conn_on_timeout(quiche_conn *conn);
int quiche_conn_close(quiche_conn *conn, _Bool app, uint64_t err, uint8_t *reason, size_t reason_len);
void quiche_conn_application_proto(quiche_conn *conn, uint8_t **out, size_t *out_len);
void quiche_conn_peer_cert(quiche_conn *conn, uint8_t **out, size_t *out_len);
_Bool quiche_conn_is_established(quiche_conn *conn);
_Bool quiche_conn_is_closed(quiche_conn *conn);
_Bool quiche_conn_peer_error(quiche_conn *conn, _Bool *is_app, uint64_t *error_code, uint8_t **reason, size_t *reason_len);
void quiche_conn_stats(quiche_conn *conn, quiche_stats *out);
int quiche_conn_path_stats(quiche_conn *conn, size_t idx, quiche_path_stats *out);
ssize_t quiche_conn_dgram_max_writable_len(quiche_conn *conn);
ssize_t quiche_conn_dgram_recv(quiche_conn *conn, uint8_t *buf, size_t buf_len);
ssize_t quiche_conn_dgram_send(quiche_conn *conn, uint8_t *buf, size_t buf_len);
ssize_t quiche_conn_send_ack_eliciting(quiche_conn *conn);
void quiche_conn_free(quiche_conn *conn);
';
    private FFI $ffi;
    private static FFI $staticFFI;
    private static \WeakMap $__arrayWeakMap;
    private array $__literalStrings = [];
    public function __construct(?string $pathToSoFile = self::SOFILE) {
        $this->ffi = FFI::cdef(self::HEADER_DEF, $pathToSoFile);
    }

    public static function cast(iQuiche $from, string $to): iQuiche {
        if (!is_a($to, iQuiche::class, true)) {
            throw new \LogicException("Cannot cast to a non-wrapper type");
        }
        return new $to(self::$staticFFI->cast($to::getType(), $from->getData()));
    }

    public static function makeArray(string $class, int|array $elements): iQuiche {
        $type = $class::getType();
        if (substr($type, -1) !== "*") {
            throw new \LogicException("Attempting to make a non-pointer element into an array");
        }
        if (is_int($elements)) {
            $cdata = self::$staticFFI->new(substr($type, 0, -1) . "[$elements]");
        } else {
            $cdata = self::$staticFFI->new(substr($type, 0, -1) . "[" . count($elements) . "]");
            foreach ($elements as $key => $raw) {
                $cdata[$key] = \is_scalar($raw) ? \is_int($raw) && $type === "char*" ? \chr($raw) : $raw : $raw->getData();
            }
        }
        $object = new $class(self::$staticFFI->cast($type, \FFI::addr($cdata)));
        self::$__arrayWeakMap[$object] = $cdata;
        return $object;
    }

    public static function sizeof($classOrObject): int {
        if (is_object($classOrObject) && $classOrObject instanceof iQuiche) {
            return FFI::sizeof($classOrObject->getData());
        } elseif (is_a($classOrObject, iQuiche::class, true)) {
            return FFI::sizeof(self::$staticFFI->type($classOrObject::getType()));
        } else {
            throw new \LogicException("Unknown class/object passed to sizeof()");
        }
    }

    public function getFFI(): FFI {
        return $this->ffi;
    }


    public function __get(string $name) {
        switch($name) {
            case 'tzname': return new string_ptr($this->ffi->tzname);
            case 'getdate_err': return $this->ffi->getdate_err;
            case 'timezone': return $this->ffi->timezone;
            case 'daylight': return $this->ffi->daylight;
            case 'in6addr_any': return new struct_in6_addr($this->ffi->in6addr_any);
            case 'in6addr_loopback': return new struct_in6_addr($this->ffi->in6addr_loopback);
            case 'in6addr_nodelocal_allnodes': return new struct_in6_addr($this->ffi->in6addr_nodelocal_allnodes);
            case 'in6addr_linklocal_allnodes': return new struct_in6_addr($this->ffi->in6addr_linklocal_allnodes);
            case 'in6addr_linklocal_allrouters': return new struct_in6_addr($this->ffi->in6addr_linklocal_allrouters);
            case 'in6addr_linklocal_allv2routers': return new struct_in6_addr($this->ffi->in6addr_linklocal_allv2routers);
            default: return $this->ffi->$name;
        }
    }
    public function __set(string $name, $value) {
        switch($name) {
            case 'tzname': (new string_ptr($this->ffi->tzname))->set($value); break;
            case 'getdate_err': $this->ffi->getdate_err = $value; break;
            case 'timezone': $this->ffi->timezone = $value; break;
            case 'daylight': $this->ffi->daylight = $value; break;
            case 'in6addr_any': (new struct_in6_addr($this->ffi->in6addr_any))->set($value); break;
            case 'in6addr_loopback': (new struct_in6_addr($this->ffi->in6addr_loopback))->set($value); break;
            case 'in6addr_nodelocal_allnodes': (new struct_in6_addr($this->ffi->in6addr_nodelocal_allnodes))->set($value); break;
            case 'in6addr_linklocal_allnodes': (new struct_in6_addr($this->ffi->in6addr_linklocal_allnodes))->set($value); break;
            case 'in6addr_linklocal_allrouters': (new struct_in6_addr($this->ffi->in6addr_linklocal_allrouters))->set($value); break;
            case 'in6addr_linklocal_allv2routers': (new struct_in6_addr($this->ffi->in6addr_linklocal_allv2routers))->set($value); break;
            default: return $this->ffi->$name;
        }
    }
    public function __allocCachedString(string $str): FFI\CData {
        return $this->__literalStrings[$str] ??= string_::ownedZero($str)->getData();
    }
    public function quiche_enable_debug_logging(void_ptr | function_type_ptr | null | array $cb, iQuiche_ptr | null | array $argp): int {
        $__ffi_internal_refscb = [];
        if (\is_array($cb)) {
            $_ = $this->ffi->new("function type[" . \count($cb) . "]");
            $_i = 0;
            if ($cb) {
                if ($_ref = \ReflectionReference::fromArrayElement($cb, \key($cb))) {
                    foreach ($cb as $_k => $_v) {
                        $__ffi_internal_refscb[$_i] = &$cb[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v->getData();
                        }
                    }
                    $__ffi_internal_originalcb = $cb = $_;
                } else {
                    foreach ($cb as $_v) {
                        $_[$_i++] = $_v?->getData();
                    }
                    $cb = $_;
                }
            }
        } else {
            $cb = $cb?->getData();
            if ($cb !== null) {
                $cb = $this->ffi->cast("void(*)(char*, void*)", $cb);
            }
        }
        $__ffi_internal_refsargp = [];
        if (\is_array($argp)) {
            $_ = $this->ffi->new("void[" . \count($argp) . "]");
            $_i = 0;
            if ($argp) {
                if ($_ref = \ReflectionReference::fromArrayElement($argp, \key($argp))) {
                    foreach ($argp as $_k => $_v) {
                        $__ffi_internal_refsargp[$_i] = &$argp[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v->getData();
                        }
                    }
                    $__ffi_internal_originalargp = $argp = $_;
                } else {
                    foreach ($argp as $_v) {
                        $_[$_i++] = $_v?->getData();
                    }
                    $argp = $_;
                }
            }
        } else {
            $argp = $argp?->getData();
            if ($argp !== null) {
                $argp = $this->ffi->cast("void*", $argp);
            }
        }
        $result = $this->ffi->quiche_enable_debug_logging($cb, $argp);
        foreach ($__ffi_internal_refscb as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $this->ffi->new("function type");
            \FFI::addr($__ffi_internal_ref_v)[0] = $__ffi_internal_originalcb[$_k];
            if ($__ffi_internal_ref_v !== null) {
                $__ffi_internal_ref_v = new function_type($__ffi_internal_ref_v, [NULL, 'string_', 'void_ptr']);
            }
        }
        foreach ($__ffi_internal_refsargp as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $this->ffi->new("void");
            \FFI::addr($__ffi_internal_ref_v)[0] = $__ffi_internal_originalargp[$_k];
            if ($__ffi_internal_ref_v !== null) {
                $__ffi_internal_ref_v = new void($__ffi_internal_ref_v);
            }
        }
        return $result;
    }
    public function quiche_config_new(int $version): ?struct_quiche_config_ptr {
        $result = $this->ffi->quiche_config_new($version);
        return $result === null ? null : new struct_quiche_config_ptr($result);
    }
    public function quiche_config_load_cert_chain_from_pem_file(void_ptr | struct_quiche_config_ptr | null | array $config, void_ptr | string_ | null | string | array $path): int {
        $config = $config?->getData();
        if (\is_string($path)) {
            $path = string_::ownedZero($path)->getData();
        }
        $result = $this->ffi->quiche_config_load_cert_chain_from_pem_file($config, $path);
        return $result;
    }
    public function quiche_config_load_priv_key_from_pem_file(void_ptr | struct_quiche_config_ptr | null | array $config, void_ptr | string_ | null | string | array $path): int {
        $config = $config?->getData();
        if (\is_string($path)) {
            $path = string_::ownedZero($path)->getData();
        }
        $result = $this->ffi->quiche_config_load_priv_key_from_pem_file($config, $path);
        return $result;
    }
    public function quiche_config_load_verify_locations_from_file(void_ptr | struct_quiche_config_ptr | null | array $config, void_ptr | string_ | null | string | array $path): int {
        $config = $config?->getData();
        if (\is_string($path)) {
            $path = string_::ownedZero($path)->getData();
        }
        $result = $this->ffi->quiche_config_load_verify_locations_from_file($config, $path);
        return $result;
    }
    public function quiche_config_load_verify_locations_from_directory(void_ptr | struct_quiche_config_ptr | null | array $config, void_ptr | string_ | null | string | array $path): int {
        $config = $config?->getData();
        if (\is_string($path)) {
            $path = string_::ownedZero($path)->getData();
        }
        $result = $this->ffi->quiche_config_load_verify_locations_from_directory($config, $path);
        return $result;
    }
    public function quiche_config_verify_peer(void_ptr | struct_quiche_config_ptr | null | array $config, int $v): void {
        $config = $config?->getData();
        $this->ffi->quiche_config_verify_peer($config, $v);
    }
    public function quiche_config_log_keys(void_ptr | struct_quiche_config_ptr | null | array $config): void {
        $config = $config?->getData();
        $this->ffi->quiche_config_log_keys($config);
    }
    public function quiche_config_set_application_protos(void_ptr | struct_quiche_config_ptr | null | array $config, void_ptr | uint8_t_ptr | null | string | array $protos, int $protos_len): int {
        $config = $config?->getData();
        $protos = $protos?->getData();
        $result = $this->ffi->quiche_config_set_application_protos($config, $protos, $protos_len);
        return $result;
    }
    public function quiche_config_set_max_idle_timeout(void_ptr | struct_quiche_config_ptr | null | array $config, int $v): void {
        $config = $config?->getData();
        $this->ffi->quiche_config_set_max_idle_timeout($config, $v);
    }
    public function quiche_config_set_initial_max_data(void_ptr | struct_quiche_config_ptr | null | array $config, int $v): void {
        $config = $config?->getData();
        $this->ffi->quiche_config_set_initial_max_data($config, $v);
    }
    public function quiche_config_set_initial_max_stream_data_bidi_local(void_ptr | struct_quiche_config_ptr | null | array $config, int $v): void {
        $config = $config?->getData();
        $this->ffi->quiche_config_set_initial_max_stream_data_bidi_local($config, $v);
    }
    public function quiche_config_set_initial_max_stream_data_bidi_remote(void_ptr | struct_quiche_config_ptr | null | array $config, int $v): void {
        $config = $config?->getData();
        $this->ffi->quiche_config_set_initial_max_stream_data_bidi_remote($config, $v);
    }
    public function quiche_config_set_initial_max_stream_data_uni(void_ptr | struct_quiche_config_ptr | null | array $config, int $v): void {
        $config = $config?->getData();
        $this->ffi->quiche_config_set_initial_max_stream_data_uni($config, $v);
    }
    public function quiche_config_set_initial_max_streams_bidi(void_ptr | struct_quiche_config_ptr | null | array $config, int $v): void {
        $config = $config?->getData();
        $this->ffi->quiche_config_set_initial_max_streams_bidi($config, $v);
    }
    public function quiche_config_set_initial_max_streams_uni(void_ptr | struct_quiche_config_ptr | null | array $config, int $v): void {
        $config = $config?->getData();
        $this->ffi->quiche_config_set_initial_max_streams_uni($config, $v);
    }
    public function quiche_config_enable_dgram(void_ptr | struct_quiche_config_ptr | null | array $config, int $enabled, int $recv_queue_len, int $send_queue_len): void {
        $config = $config?->getData();
        $this->ffi->quiche_config_enable_dgram($config, $enabled, $recv_queue_len, $send_queue_len);
    }
    public function quiche_config_set_stateless_reset_token(void_ptr | struct_quiche_config_ptr | null | array $config, void_ptr | uint8_t_ptr | null | string | array $v): void {
        $config = $config?->getData();
        if (\is_string($v)) {
            $__ffi_str_v = string_::ownedZero($v)->getData();
            $v = $this->ffi->cast("uint8_t*", \FFI::addr($__ffi_str_v));
        }
        $this->ffi->quiche_config_set_stateless_reset_token($config, $v);
    }
    public function quiche_config_free(void_ptr | struct_quiche_config_ptr | null | array $config): void {
        $config = $config?->getData();
        $this->ffi->quiche_config_free($config);
    }
    public function quiche_header_info(void_ptr | uint8_t_ptr | null | string | array $buf, int $buf_len, int $dcil, void_ptr | uint32_t_ptr | null | array $version, void_ptr | uint8_t_ptr | null | string | array $type, void_ptr | uint8_t_ptr | null | string | array $scid, void_ptr | size_t_ptr | null | array $scid_len, void_ptr | uint8_t_ptr | null | string | array $dcid, void_ptr | size_t_ptr | null | array $dcid_len, void_ptr | uint8_t_ptr | null | string | array $token, void_ptr | size_t_ptr | null | array $token_len): int {
        if (\is_string($buf)) {
            $__ffi_str_buf = string_::ownedZero($buf)->getData();
            $buf = $this->ffi->cast("uint8_t*", \FFI::addr($__ffi_str_buf));
        }
        $__ffi_internal_refsversion = [];
        if (\is_array($version)) {
            $_ = $this->ffi->new("uint32_t[" . \count($version) . "]");
            $_i = 0;
            if ($version) {
                    foreach ($version as $_k => $_v) {
                        $__ffi_internal_refsversion[$_i] = &$version[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v;
                        }
                    }
                    $__ffi_internal_originalversion = $version = $_;
            }
        }
        $__ffi_internal_refstype = [];
        if (\is_array($type)) {
            $_ = $this->ffi->new("uint8_t[" . \count($type) . "]");
            $_i = 0;
            if ($type) {
                    foreach ($type as $_k => $_v) {
                        $__ffi_internal_refstype[$_i] = &$type[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v;
                        }
                    }
                    $__ffi_internal_originaltype = $type = $_;
            }
        }
        $scid = $scid?->getData();
        $__ffi_internal_refsscid_len = [];
        if (\is_array($scid_len)) {
            $_ = $this->ffi->new("size_t[" . \count($scid_len) . "]");
            $_i = 0;
            if ($scid_len) {
                    foreach ($scid_len as $_k => $_v) {
                        $__ffi_internal_refsscid_len[$_i] = &$scid_len[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v;
                        }
                    }
                    $__ffi_internal_originalscid_len = $scid_len = $_;
            }
        }
        $dcid = $dcid?->getData();
        $__ffi_internal_refsdcid_len = [];
        if (\is_array($dcid_len)) {
            $_ = $this->ffi->new("size_t[" . \count($dcid_len) . "]");
            $_i = 0;
            if ($dcid_len) {
                    foreach ($dcid_len as $_k => $_v) {
                        $__ffi_internal_refsdcid_len[$_i] = &$dcid_len[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v;
                        }
                    }
                    $__ffi_internal_originaldcid_len = $dcid_len = $_;
            }
        }
        $token = $token?->getData();
        $__ffi_internal_refstoken_len = [];
        if (\is_array($token_len)) {
            $_ = $this->ffi->new("size_t[" . \count($token_len) . "]");
            $_i = 0;
            if ($token_len) {
                    foreach ($token_len as $_k => $_v) {
                        $__ffi_internal_refstoken_len[$_i] = &$token_len[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v;
                        }
                    }
                    $__ffi_internal_originaltoken_len = $token_len = $_;
            }
        }
        $result = $this->ffi->quiche_header_info($buf, $buf_len, $dcil, $version, $type, $scid, $scid_len, $dcid, $dcid_len, $token, $token_len);
        foreach ($__ffi_internal_refsversion as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $__ffi_internal_originalversion[$_k];
        }
        foreach ($__ffi_internal_refstype as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $__ffi_internal_originaltype[$_k];
        }
        foreach ($__ffi_internal_refsscid_len as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $__ffi_internal_originalscid_len[$_k];
        }
        foreach ($__ffi_internal_refsdcid_len as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $__ffi_internal_originaldcid_len[$_k];
        }
        foreach ($__ffi_internal_refstoken_len as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $__ffi_internal_originaltoken_len[$_k];
        }
        return $result;
    }
    public function quiche_accept(void_ptr | uint8_t_ptr | null | string | array $scid, int $scid_len, void_ptr | uint8_t_ptr | null | string | array $odcid, int $odcid_len, void_ptr | struct_sockaddr_ptr | null | array $local, int $local_len, void_ptr | struct_sockaddr_ptr | null | array $peer, int $peer_len, void_ptr | struct_quiche_config_ptr | null | array $config): ?struct_quiche_conn_ptr {
        $scid = $scid?->getData();
        if (\is_string($odcid)) {
            $__ffi_str_odcid = string_::ownedZero($odcid)->getData();
            $odcid = $this->ffi->cast("uint8_t*", \FFI::addr($__ffi_str_odcid));
        }
        $local = $local?->getData();
        if ($local !== null) {
            $local = $this->ffi->cast("struct sockaddr*", $local);
        }
        $peer = $peer?->getData();
        if ($peer !== null) {
            $peer = $this->ffi->cast("struct sockaddr*", $peer);
        }
        $config = $config?->getData();
        $result = $this->ffi->quiche_accept($scid, $scid_len, $odcid, $odcid_len, $local, $local_len, $peer, $peer_len, $config);
        return $result === null ? null : new struct_quiche_conn_ptr($result);
    }
    public function quiche_connect(void_ptr | string_ | null | string | array $server_name, void_ptr | uint8_t_ptr | null | string | array $scid, int $scid_len, void_ptr | struct_sockaddr_ptr | null | array $local, int $local_len, void_ptr | struct_sockaddr_ptr | null | array $peer, int $peer_len, void_ptr | struct_quiche_config_ptr | null | array $config): ?struct_quiche_conn_ptr {
        if (\is_string($server_name)) {
            $server_name = string_::ownedZero($server_name)->getData();
        }
        if (\is_string($scid)) {
            $__ffi_str_scid = string_::ownedZero($scid)->getData();
            $scid = $this->ffi->cast("uint8_t*", \FFI::addr($__ffi_str_scid));
        }
        $local = $local?->getData();
        if ($local !== null) {
            $local = $this->ffi->cast("struct sockaddr*", $local);
        }
        $peer = $peer?->getData();
        if ($peer !== null) {
            $peer = $this->ffi->cast("struct sockaddr*", $peer);
        }
        $config = $config?->getData();
        $result = $this->ffi->quiche_connect($server_name, $scid, $scid_len, $local, $local_len, $peer, $peer_len, $config);
        return $result === null ? null : new struct_quiche_conn_ptr($result);
    }
    public function quiche_negotiate_version(void_ptr | uint8_t_ptr | null | string | array $scid, int $scid_len, void_ptr | uint8_t_ptr | null | string | array $dcid, int $dcid_len, void_ptr | uint8_t_ptr | null | string | array $out, int $out_len): int {
        $scid = $scid?->getData();
        $dcid = $dcid?->getData();
        $out = $out?->getData();
        $result = $this->ffi->quiche_negotiate_version($scid, $scid_len, $dcid, $dcid_len, $out, $out_len);
        return $result;
    }
    public function quiche_retry(void_ptr | uint8_t_ptr | null | string | array $scid, int $scid_len, void_ptr | uint8_t_ptr | null | string | array $dcid, int $dcid_len, void_ptr | uint8_t_ptr | null | string | array $new_scid, int $new_scid_len, void_ptr | uint8_t_ptr | null | string | array $token, int $token_len, int $version, void_ptr | uint8_t_ptr | null | string | array $out, int $out_len): int {
        $scid = $scid?->getData();
        $dcid = $dcid?->getData();
        if (\is_string($new_scid)) {
            $__ffi_str_new_scid = string_::ownedZero($new_scid)->getData();
            $new_scid = $this->ffi->cast("uint8_t*", \FFI::addr($__ffi_str_new_scid));
        }
        if (\is_string($token)) {
            $__ffi_str_token = string_::ownedZero($token)->getData();
            $token = $this->ffi->cast("uint8_t*", \FFI::addr($__ffi_str_token));
        }
        $out = $out?->getData();
        $result = $this->ffi->quiche_retry($scid, $scid_len, $dcid, $dcid_len, $new_scid, $new_scid_len, $token, $token_len, $version, $out, $out_len);
        return $result;
    }
    public function quiche_version_is_supported(int $version): int {
        $result = $this->ffi->quiche_version_is_supported($version);
        return $result;
    }
    public function quiche_conn_set_keylog_path(void_ptr | struct_quiche_conn_ptr | null | array $conn, void_ptr | string_ | null | string | array $path): int {
        $conn = $conn?->getData();
        if (\is_string($path)) {
            $path = string_::ownedZero($path)->getData();
        }
        $result = $this->ffi->quiche_conn_set_keylog_path($conn, $path);
        return $result;
    }
    public function quiche_conn_recv(void_ptr | struct_quiche_conn_ptr | null | array $conn, void_ptr | uint8_t_ptr | null | string | array $buf, int $buf_len, void_ptr | quiche_recv_info_ptr | null | array $info): int {
        $conn = $conn?->getData();
        if (\is_string($buf)) {
            $__ffi_str_buf = string_::ownedZero($buf)->getData();
            $buf = $this->ffi->cast("uint8_t*", \FFI::addr($__ffi_str_buf));
        }
        $info = $info?->getData();
        if ($info !== null) {
            $info = $this->ffi->cast("quiche_recv_info*", $info);
        }
        $result = $this->ffi->quiche_conn_recv($conn, $buf, $buf_len, $info);
        return $result;
    }
    public function quiche_conn_send(void_ptr | struct_quiche_conn_ptr | null | array $conn, void_ptr | uint8_t_ptr | null | string | array $out, int $out_len, void_ptr | quiche_send_info_ptr | null | array $out_info): int {
        $conn = $conn?->getData();
        $out = $out?->getData();
        $out_info = $out_info?->getData();
        if ($out_info !== null) {
            $out_info = $this->ffi->cast("quiche_send_info*", $out_info);
        }
        $result = $this->ffi->quiche_conn_send($conn, $out, $out_len, $out_info);
        return $result;
    }
    public function quiche_conn_stream_recv(void_ptr | struct_quiche_conn_ptr | null | array $conn, int $stream_id, void_ptr | uint8_t_ptr | null | string | array $out, int $buf_len, void_ptr | _Bool_ptr | null | array $fin): int {
        $conn = $conn?->getData();
        $out = $out?->getData();
        $__ffi_internal_refsfin = [];
        if (\is_array($fin)) {
            $_ = $this->ffi->new("_Bool[" . \count($fin) . "]");
            $_i = 0;
            if ($fin) {
                    foreach ($fin as $_k => $_v) {
                        $__ffi_internal_refsfin[$_i] = &$fin[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v;
                        }
                    }
                    $__ffi_internal_originalfin = $fin = $_;
            }
        }
        $result = $this->ffi->quiche_conn_stream_recv($conn, $stream_id, $out, $buf_len, $fin);
        foreach ($__ffi_internal_refsfin as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $__ffi_internal_originalfin[$_k];
        }
        return $result;
    }
    public function quiche_conn_stream_send(void_ptr | struct_quiche_conn_ptr | null | array $conn, int $stream_id, void_ptr | uint8_t_ptr | null | string | array $buf, int $buf_len, int $fin): int {
        $conn = $conn?->getData();
        if (\is_string($buf)) {
            $__ffi_str_buf = string_::ownedZero($buf)->getData();
            $buf = $this->ffi->cast("uint8_t*", \FFI::addr($__ffi_str_buf));
        } else {
            $buf = $buf?->getData();
        }
        $result = $this->ffi->quiche_conn_stream_send($conn, $stream_id, $buf, $buf_len, $fin);
        return $result;
    }
    public function quiche_conn_stream_priority(void_ptr | struct_quiche_conn_ptr | null | array $conn, int $stream_id, int $urgency, int $incremental): int {
        $conn = $conn?->getData();
        $result = $this->ffi->quiche_conn_stream_priority($conn, $stream_id, $urgency, $incremental);
        return $result;
    }
    public function quiche_conn_stream_shutdown(void_ptr | struct_quiche_conn_ptr | null | array $conn, int $stream_id, int $direction, int $err): int {
        $conn = $conn?->getData();
        $result = $this->ffi->quiche_conn_stream_shutdown($conn, $stream_id, $direction, $err);
        return $result;
    }
    public function quiche_conn_stream_readable_next(void_ptr | struct_quiche_conn_ptr | null | array $conn): int {
        $conn = $conn?->getData();
        $result = $this->ffi->quiche_conn_stream_readable_next($conn);
        return $result;
    }
    public function quiche_conn_stream_writable(void_ptr | struct_quiche_conn_ptr | null | array $conn, int $stream_id, int $len): int {
        $conn = $conn?->getData();
        $result = $this->ffi->quiche_conn_stream_writable($conn, $stream_id, $len);
        return $result;
    }
    public function quiche_conn_stream_writable_next(void_ptr | struct_quiche_conn_ptr | null | array $conn): int {
        $conn = $conn?->getData();
        $result = $this->ffi->quiche_conn_stream_writable_next($conn);
        return $result;
    }
    public function quiche_conn_timeout_as_nanos(void_ptr | struct_quiche_conn_ptr | null | array $conn): int {
        $conn = $conn?->getData();
        $result = $this->ffi->quiche_conn_timeout_as_nanos($conn);
        return $result;
    }
    public function quiche_conn_on_timeout(void_ptr | struct_quiche_conn_ptr | null | array $conn): void {
        $conn = $conn?->getData();
        $this->ffi->quiche_conn_on_timeout($conn);
    }
    public function quiche_conn_close(void_ptr | struct_quiche_conn_ptr | null | array $conn, int $app, int $err, void_ptr | uint8_t_ptr | null | string | array $reason, int $reason_len): int {
        $conn = $conn?->getData();
        if (\is_string($reason)) {
            $__ffi_str_reason = string_::ownedZero($reason)->getData();
            $reason = $this->ffi->cast("uint8_t*", \FFI::addr($__ffi_str_reason));
        }
        $result = $this->ffi->quiche_conn_close($conn, $app, $err, $reason, $reason_len);
        return $result;
    }
    public function quiche_conn_application_proto(void_ptr | struct_quiche_conn_ptr | null | array $conn, void_ptr | uint8_t_ptr_ptr | null | array $out, void_ptr | size_t_ptr | null | array $out_len): void {
        $conn = $conn?->getData();
        $__ffi_internal_refsout = [];
        if (\is_array($out)) {
            $_ = $this->ffi->new("uint8_t*[" . \count($out) . "]");
            $_i = 0;
            if ($out) {
                    foreach ($out as $_k => $_v) {
                        $__ffi_internal_refsout[$_i] = &$out[$_k];
                        $_[$_i++] = $_v?->getData();
                    }
                    $__ffi_internal_originalout = $out = $_;
            }
        }
        $__ffi_internal_refsout_len = [];
        if (\is_array($out_len)) {
            $_ = $this->ffi->new("size_t[" . \count($out_len) . "]");
            $_i = 0;
            if ($out_len) {
                    foreach ($out_len as $_k => $_v) {
                        $__ffi_internal_refsout_len[$_i] = &$out_len[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v;
                        }
                    }
                    $__ffi_internal_originalout_len = $out_len = $_;
            }
        }
        $this->ffi->quiche_conn_application_proto($conn, $out, $out_len);
        foreach ($__ffi_internal_refsout as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $__ffi_internal_originalout[$_k];
            if ($__ffi_internal_ref_v !== null) {
                $__ffi_internal_ref_v = new uint8_t_ptr($__ffi_internal_ref_v);
            }
        }
        foreach ($__ffi_internal_refsout_len as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $__ffi_internal_originalout_len[$_k];
        }
    }
    public function quiche_conn_peer_cert(void_ptr | struct_quiche_conn_ptr | null | array $conn, void_ptr | uint8_t_ptr_ptr | null | array $out, void_ptr | size_t_ptr | null | array $out_len): void {
        $conn = $conn?->getData();
        $__ffi_internal_refsout = [];
        if (\is_array($out)) {
            $_ = $this->ffi->new("uint8_t*[" . \count($out) . "]");
            $_i = 0;
            if ($out) {
                    foreach ($out as $_k => $_v) {
                        $__ffi_internal_refsout[$_i] = &$out[$_k];
                        $_[$_i++] = $_v?->getData();
                    }
                    $__ffi_internal_originalout = $out = $_;
            }
        }
        $__ffi_internal_refsout_len = [];
        if (\is_array($out_len)) {
            $_ = $this->ffi->new("size_t[" . \count($out_len) . "]");
            $_i = 0;
            if ($out_len) {
                    foreach ($out_len as $_k => $_v) {
                        $__ffi_internal_refsout_len[$_i] = &$out_len[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v;
                        }
                    }
                    $__ffi_internal_originalout_len = $out_len = $_;
            }
        }
        $this->ffi->quiche_conn_peer_cert($conn, $out, $out_len);
        foreach ($__ffi_internal_refsout as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $__ffi_internal_originalout[$_k];
            if ($__ffi_internal_ref_v !== null) {
                $__ffi_internal_ref_v = new uint8_t_ptr($__ffi_internal_ref_v);
            }
        }
        foreach ($__ffi_internal_refsout_len as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $__ffi_internal_originalout_len[$_k];
        }
    }
    public function quiche_conn_is_established(void_ptr | struct_quiche_conn_ptr | null | array $conn): int {
        $conn = $conn?->getData();
        $result = $this->ffi->quiche_conn_is_established($conn);
        return $result;
    }
    public function quiche_conn_is_closed(void_ptr | struct_quiche_conn_ptr | null | array $conn): int {
        $conn = $conn?->getData();
        $result = $this->ffi->quiche_conn_is_closed($conn);
        return $result;
    }
    public function quiche_conn_peer_error(void_ptr | struct_quiche_conn_ptr | null | array $conn, void_ptr | _Bool_ptr | null | array $is_app, void_ptr | uint64_t_ptr | null | array $error_code, void_ptr | uint8_t_ptr_ptr | null | array $reason, void_ptr | size_t_ptr | null | array $reason_len): int {
        $conn = $conn?->getData();
        $__ffi_internal_refsis_app = [];
        if (\is_array($is_app)) {
            $_ = $this->ffi->new("_Bool[" . \count($is_app) . "]");
            $_i = 0;
            if ($is_app) {
                    foreach ($is_app as $_k => $_v) {
                        $__ffi_internal_refsis_app[$_i] = &$is_app[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v;
                        }
                    }
                    $__ffi_internal_originalis_app = $is_app = $_;
            }
        }
        $__ffi_internal_refserror_code = [];
        if (\is_array($error_code)) {
            $_ = $this->ffi->new("uint64_t[" . \count($error_code) . "]");
            $_i = 0;
            if ($error_code) {
                    foreach ($error_code as $_k => $_v) {
                        $__ffi_internal_refserror_code[$_i] = &$error_code[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v;
                        }
                    }
                    $__ffi_internal_originalerror_code = $error_code = $_;
            }
        }
        $__ffi_internal_refsreason = [];
        if (\is_array($reason)) {
            $_ = $this->ffi->new("uint8_t*[" . \count($reason) . "]");
            $_i = 0;
            if ($reason) {
                    foreach ($reason as $_k => $_v) {
                        $__ffi_internal_refsreason[$_i] = &$reason[$_k];
                        $_[$_i++] = $_v?->getData();
                    }
                    $__ffi_internal_originalreason = $reason = $_;
            }
        }
        $__ffi_internal_refsreason_len = [];
        if (\is_array($reason_len)) {
            $_ = $this->ffi->new("size_t[" . \count($reason_len) . "]");
            $_i = 0;
            if ($reason_len) {
                    foreach ($reason_len as $_k => $_v) {
                        $__ffi_internal_refsreason_len[$_i] = &$reason_len[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v;
                        }
                    }
                    $__ffi_internal_originalreason_len = $reason_len = $_;
            }
        }
        $result = $this->ffi->quiche_conn_peer_error($conn, $is_app, $error_code, $reason, $reason_len);
        foreach ($__ffi_internal_refsis_app as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $__ffi_internal_originalis_app[$_k];
        }
        foreach ($__ffi_internal_refserror_code as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $__ffi_internal_originalerror_code[$_k];
        }
        foreach ($__ffi_internal_refsreason as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $__ffi_internal_originalreason[$_k];
            if ($__ffi_internal_ref_v !== null) {
                $__ffi_internal_ref_v = new uint8_t_ptr($__ffi_internal_ref_v);
            }
        }
        foreach ($__ffi_internal_refsreason_len as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $__ffi_internal_originalreason_len[$_k];
        }
        return $result;
    }
    public function quiche_conn_stats(void_ptr | struct_quiche_conn_ptr | null | array $conn, void_ptr | quiche_stats_ptr | null | array $out): void {
        $conn = $conn?->getData();
        $__ffi_internal_refsout = [];
        if (\is_array($out)) {
            $_ = $this->ffi->new("quiche_stats[" . \count($out) . "]");
            $_i = 0;
            if ($out) {
                    foreach ($out as $_k => $_v) {
                        $__ffi_internal_refsout[$_i] = &$out[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v->getData();
                        }
                    }
                    $__ffi_internal_originalout = $out = $_;
            }
        }
        $this->ffi->quiche_conn_stats($conn, $out);
        foreach ($__ffi_internal_refsout as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $this->ffi->new("quiche_stats");
            \FFI::addr($__ffi_internal_ref_v)[0] = $__ffi_internal_originalout[$_k];
            if ($__ffi_internal_ref_v !== null) {
                $__ffi_internal_ref_v = new quiche_stats($__ffi_internal_ref_v);
            }
        }
    }
    public function quiche_conn_path_stats(void_ptr | struct_quiche_conn_ptr | null | array $conn, int $idx, void_ptr | quiche_path_stats_ptr | null | array $out): int {
        $conn = $conn?->getData();
        $__ffi_internal_refsout = [];
        if (\is_array($out)) {
            $_ = $this->ffi->new("quiche_path_stats[" . \count($out) . "]");
            $_i = 0;
            if ($out) {
                    foreach ($out as $_k => $_v) {
                        $__ffi_internal_refsout[$_i] = &$out[$_k];
                        if ($_v !== null) {
                            $_[$_i++] = $_v->getData();
                        }
                    }
                    $__ffi_internal_originalout = $out = $_;
            }
        }
        $result = $this->ffi->quiche_conn_path_stats($conn, $idx, $out);
        foreach ($__ffi_internal_refsout as $_k => &$__ffi_internal_ref_v) {
            $__ffi_internal_ref_v = $this->ffi->new("quiche_path_stats");
            \FFI::addr($__ffi_internal_ref_v)[0] = $__ffi_internal_originalout[$_k];
            if ($__ffi_internal_ref_v !== null) {
                $__ffi_internal_ref_v = new quiche_path_stats($__ffi_internal_ref_v);
            }
        }
        return $result;
    }
    public function quiche_conn_dgram_max_writable_len(void_ptr | struct_quiche_conn_ptr | null | array $conn): int {
        $conn = $conn?->getData();
        $result = $this->ffi->quiche_conn_dgram_max_writable_len($conn);
        return $result;
    }
    public function quiche_conn_dgram_recv(void_ptr | struct_quiche_conn_ptr | null | array $conn, void_ptr | uint8_t_ptr | null | string | array $buf, int $buf_len): int {
        $conn = $conn?->getData();
        $buf = $buf?->getData();
        $result = $this->ffi->quiche_conn_dgram_recv($conn, $buf, $buf_len);
        return $result;
    }
    public function quiche_conn_dgram_send(void_ptr | struct_quiche_conn_ptr | null | array $conn, void_ptr | uint8_t_ptr | null | string | array $buf, int $buf_len): int {
        $conn = $conn?->getData();
        if (\is_string($buf)) {
            $__ffi_str_buf = string_::ownedZero($buf)->getData();
            $buf = $this->ffi->cast("uint8_t*", \FFI::addr($__ffi_str_buf));
        }
        $result = $this->ffi->quiche_conn_dgram_send($conn, $buf, $buf_len);
        return $result;
    }
    public function quiche_conn_send_ack_eliciting(void_ptr | struct_quiche_conn_ptr | null | array $conn): int {
        $conn = $conn?->getData();
        $result = $this->ffi->quiche_conn_send_ack_eliciting($conn);
        return $result;
    }
    public function quiche_conn_free(void_ptr | struct_quiche_conn_ptr | null | array $conn): void {
        $conn = $conn?->getData();
        $this->ffi->quiche_conn_free($conn);
    }
}

class string_ implements iQuiche, iQuiche_ptr, \ArrayAccess {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(string_ $other): bool { return $this->data == $other->data; }
    public function addr(): string_ptr { return new string_ptr(FFI::addr($this->data)); }
    public function deref(int $n = 0): int { return \ord($this->data[$n]); }
    #[\ReturnTypeWillChange] public function offsetGet($offset): int { return $this->deref($offset); }
    #[\ReturnTypeWillChange] public function offsetExists($offset): bool { return !FFI::isNull($this->data); }
    #[\ReturnTypeWillChange] public function offsetUnset($offset): void { throw new \Error("Cannot unset C structures"); }
    #[\ReturnTypeWillChange] public function offsetSet($offset, $value): void { $this->data[$offset] = \chr($value); }
    public static function array(int $size = 1): self { return QuicheFFI::makeArray(self::class, $size); }
    /** @return int[] */ public function toArray(?int $length = null): array { $ret = []; if ($length === null) { $i = 0; while ("\0" !== $cur = $this->data[$i++]) { $ret[] = \ord($cur); } } else { for ($i = 0; $i < $length; ++$i) { $ret[] = \ord($this->data[$i]); } } return $ret; }
    public function toString(?int $length = null): string { return $length === null ? FFI::string($this->data) : FFI::string($this->data, $length); }
    public static function persistent(string $string): self { $str = new self(FFI::cdef()->new("char[" . \strlen($string) . "]", false)); FFI::memcpy($str->data, $string, \strlen($string)); return $str; }
    public static function owned(string $string): self { $str = new self(FFI::cdef()->new("char[" . \strlen($string) . "]", true)); FFI::memcpy($str->data, $string, \strlen($string)); return $str; }
    public static function persistentZero(string $string): self { return self::persistent("$string\0"); }
    public static function ownedZero(string $string): self { return self::owned("$string\0"); }
    public function set(int | void_ptr | string_ $value): void {
        if (\is_scalar($value)) {
            $this->data[0] = \chr($value);
        } else {
            FFI::addr($this->data)[0] = $value->getData();
        }
    }
    public static function getType(): string { return 'char*'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
/**
 * @property int $sa_len
 * @property int $sa_family
 * @property string_ $sa_data
 */
class struct_sockaddr_ptr implements iQuiche, iQuiche_ptr, \ArrayAccess {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(struct_sockaddr_ptr $other): bool { return $this->data == $other->data; }
    public function addr(): struct_sockaddr_ptr_ptr { return new struct_sockaddr_ptr_ptr(FFI::addr($this->data)); }
    public function deref(int $n = 0): struct_sockaddr { return new struct_sockaddr($this->data[$n]); }
    #[\ReturnTypeWillChange] public function offsetGet($offset): struct_sockaddr { return $this->deref($offset); }
    #[\ReturnTypeWillChange] public function offsetExists($offset): bool { return !FFI::isNull($this->data); }
    #[\ReturnTypeWillChange] public function offsetUnset($offset): void { throw new \Error("Cannot unset C structures"); }
    #[\ReturnTypeWillChange] public function offsetSet($offset, $value): void { $this->data[$offset] = $value->getData(); }
    public static function array(int $size = 1): self { return QuicheFFI::makeArray(self::class, $size); }
    /** @return struct_sockaddr[] */ public function toArray(int $length): array { $ret = []; for ($i = 0; $i < $length; ++$i) { $ret[] = new struct_sockaddr($this->data[$i]); } return $ret; }
    public function __get($prop) {
        switch ($prop) {
            case "sa_len": return $this->data[0]->sa_len;
            case "sa_family": return $this->data[0]->sa_family;
            case "sa_data": return new string_($this->data[0]->sa_data);
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function __set($prop, $value) {
        switch ($prop) {
            case "sa_len":
                $this->data[0]->sa_len = $value;
                return;
            case "sa_family":
                $this->data[0]->sa_family = $value;
                return;
            case "sa_data":
                (new string_($_ = &$this->data[0]->sa_data))->set($value);
                return;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function set(void_ptr | struct_sockaddr_ptr $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'struct sockaddr*'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
/**
 * @property int $ss_len
 * @property int $ss_family
 * @property string_ $__ss_pad1
 * @property int $__ss_align
 * @property string_ $__ss_pad2
 */
class struct_sockaddr_storage implements iQuiche {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(struct_sockaddr_storage $other): bool { return $this->data == $other->data; }
    public function addr(): struct_sockaddr_storage_ptr { return new struct_sockaddr_storage_ptr(FFI::addr($this->data)); }
    public function __get($prop) {
        switch ($prop) {
            case "ss_len": return $this->data->ss_len;
            case "ss_family": return $this->data->ss_family;
            case "__ss_pad1": return new string_($this->data->__ss_pad1);
            case "__ss_align": return $this->data->__ss_align;
            case "__ss_pad2": return new string_($this->data->__ss_pad2);
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function __set($prop, $value) {
        switch ($prop) {
            case "ss_len":
                $this->data->ss_len = $value;
                return;
            case "ss_family":
                $this->data->ss_family = $value;
                return;
            case "__ss_pad1":
                (new string_($_ = &$this->data->__ss_pad1))->set($value);
                return;
            case "__ss_align":
                $this->data->__ss_align = $value;
                return;
            case "__ss_pad2":
                (new string_($_ = &$this->data->__ss_pad2))->set($value);
                return;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function set(struct_sockaddr_storage $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'struct sockaddr_storage'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
class struct_quiche_config_ptr implements iQuiche, iQuiche_ptr, \ArrayAccess {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(struct_quiche_config_ptr $other): bool { return $this->data == $other->data; }
    public function addr(): struct_quiche_config_ptr_ptr { return new struct_quiche_config_ptr_ptr(FFI::addr($this->data)); }
    public function deref(int $n = 0): struct_quiche_config { return new struct_quiche_config($this->data[$n]); }
    #[\ReturnTypeWillChange] public function offsetGet($offset): struct_quiche_config { return $this->deref($offset); }
    #[\ReturnTypeWillChange] public function offsetExists($offset): bool { return !FFI::isNull($this->data); }
    #[\ReturnTypeWillChange] public function offsetUnset($offset): void { throw new \Error("Cannot unset C structures"); }
    #[\ReturnTypeWillChange] public function offsetSet($offset, $value): void { $this->data[$offset] = $value->getData(); }
    public static function array(int $size = 1): self { return QuicheFFI::makeArray(self::class, $size); }
    /** @return struct_quiche_config[] */ public function toArray(int $length): array { $ret = []; for ($i = 0; $i < $length; ++$i) { $ret[] = new struct_quiche_config($this->data[$i]); } return $ret; }
    public function set(void_ptr | struct_quiche_config_ptr $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'struct quiche_config*'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
class struct_quiche_conn_ptr implements iQuiche, iQuiche_ptr, \ArrayAccess {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(struct_quiche_conn_ptr $other): bool { return $this->data == $other->data; }
    public function addr(): struct_quiche_conn_ptr_ptr { return new struct_quiche_conn_ptr_ptr(FFI::addr($this->data)); }
    public function deref(int $n = 0): struct_quiche_conn { return new struct_quiche_conn($this->data[$n]); }
    #[\ReturnTypeWillChange] public function offsetGet($offset): struct_quiche_conn { return $this->deref($offset); }
    #[\ReturnTypeWillChange] public function offsetExists($offset): bool { return !FFI::isNull($this->data); }
    #[\ReturnTypeWillChange] public function offsetUnset($offset): void { throw new \Error("Cannot unset C structures"); }
    #[\ReturnTypeWillChange] public function offsetSet($offset, $value): void { $this->data[$offset] = $value->getData(); }
    public static function array(int $size = 1): self { return QuicheFFI::makeArray(self::class, $size); }
    /** @return struct_quiche_conn[] */ public function toArray(int $length): array { $ret = []; for ($i = 0; $i < $length; ++$i) { $ret[] = new struct_quiche_conn($this->data[$i]); } return $ret; }
    public function set(void_ptr | struct_quiche_conn_ptr $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'struct quiche_conn*'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
/**
 * @property struct_sockaddr_ptr $from
 * @property int $from_len
 * @property struct_sockaddr_ptr $to
 * @property int $to_len
 */
class quiche_recv_info_ptr implements iQuiche, iQuiche_ptr, \ArrayAccess {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(quiche_recv_info_ptr $other): bool { return $this->data == $other->data; }
    public function addr(): quiche_recv_info_ptr_ptr { return new quiche_recv_info_ptr_ptr(FFI::addr($this->data)); }
    public function deref(int $n = 0): quiche_recv_info { return new quiche_recv_info($this->data[$n]); }
    #[\ReturnTypeWillChange] public function offsetGet($offset): quiche_recv_info { return $this->deref($offset); }
    #[\ReturnTypeWillChange] public function offsetExists($offset): bool { return !FFI::isNull($this->data); }
    #[\ReturnTypeWillChange] public function offsetUnset($offset): void { throw new \Error("Cannot unset C structures"); }
    #[\ReturnTypeWillChange] public function offsetSet($offset, $value): void { $this->data[$offset] = $value->getData(); }
    public static function array(int $size = 1): self { return QuicheFFI::makeArray(self::class, $size); }
    /** @return quiche_recv_info[] */ public function toArray(int $length): array { $ret = []; for ($i = 0; $i < $length; ++$i) { $ret[] = new quiche_recv_info($this->data[$i]); } return $ret; }
    public function __get($prop) {
        switch ($prop) {
            case "from": return new struct_sockaddr_ptr($this->data[0]->from);
            case "from_len": return $this->data[0]->from_len;
            case "to": return new struct_sockaddr_ptr($this->data[0]->to);
            case "to_len": return $this->data[0]->to_len;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function __set($prop, $value) {
        switch ($prop) {
            case "from":
                (new struct_sockaddr_ptr($_ = &$this->data[0]->from))->set($value);
                return;
            case "from_len":
                $this->data[0]->from_len = $value;
                return;
            case "to":
                (new struct_sockaddr_ptr($_ = &$this->data[0]->to))->set($value);
                return;
            case "to_len":
                $this->data[0]->to_len = $value;
                return;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function set(void_ptr | quiche_recv_info_ptr $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'quiche_recv_info*'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
/**
 * @property struct_sockaddr_storage $from
 * @property int $from_len
 * @property struct_sockaddr_storage $to
 * @property int $to_len
 * @property struct_timespec $at
 */
class quiche_send_info_ptr implements iQuiche, iQuiche_ptr, \ArrayAccess {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(quiche_send_info_ptr $other): bool { return $this->data == $other->data; }
    public function addr(): quiche_send_info_ptr_ptr { return new quiche_send_info_ptr_ptr(FFI::addr($this->data)); }
    public function deref(int $n = 0): quiche_send_info { return new quiche_send_info($this->data[$n]); }
    #[\ReturnTypeWillChange] public function offsetGet($offset): quiche_send_info { return $this->deref($offset); }
    #[\ReturnTypeWillChange] public function offsetExists($offset): bool { return !FFI::isNull($this->data); }
    #[\ReturnTypeWillChange] public function offsetUnset($offset): void { throw new \Error("Cannot unset C structures"); }
    #[\ReturnTypeWillChange] public function offsetSet($offset, $value): void { $this->data[$offset] = $value->getData(); }
    public static function array(int $size = 1): self { return QuicheFFI::makeArray(self::class, $size); }
    /** @return quiche_send_info[] */ public function toArray(int $length): array { $ret = []; for ($i = 0; $i < $length; ++$i) { $ret[] = new quiche_send_info($this->data[$i]); } return $ret; }
    public function __get($prop) {
        switch ($prop) {
            case "from": return new struct_sockaddr_storage($this->data[0]->from);
            case "from_len": return $this->data[0]->from_len;
            case "to": return new struct_sockaddr_storage($this->data[0]->to);
            case "to_len": return $this->data[0]->to_len;
            case "at": return new struct_timespec($this->data[0]->at);
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function __set($prop, $value) {
        switch ($prop) {
            case "from":
                (new struct_sockaddr_storage($_ = &$this->data[0]->from))->set($value);
                return;
            case "from_len":
                $this->data[0]->from_len = $value;
                return;
            case "to":
                (new struct_sockaddr_storage($_ = &$this->data[0]->to))->set($value);
                return;
            case "to_len":
                $this->data[0]->to_len = $value;
                return;
            case "at":
                (new struct_timespec($_ = &$this->data[0]->at))->set($value);
                return;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function set(void_ptr | quiche_send_info_ptr $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'quiche_send_info*'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
/**
 * @property int $recv
 * @property int $sent
 * @property int $lost
 * @property int $retrans
 * @property int $sent_bytes
 * @property int $recv_bytes
 * @property int $lost_bytes
 * @property int $stream_retrans_bytes
 * @property int $paths_count
 * @property int $reset_stream_count_local
 * @property int $stopped_stream_count_local
 * @property int $reset_stream_count_remote
 * @property int $stopped_stream_count_remote
 */
class quiche_stats implements iQuiche {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(quiche_stats $other): bool { return $this->data == $other->data; }
    public function addr(): quiche_stats_ptr { return new quiche_stats_ptr(FFI::addr($this->data)); }
    public function __get($prop) {
        switch ($prop) {
            case "recv": return $this->data->recv;
            case "sent": return $this->data->sent;
            case "lost": return $this->data->lost;
            case "retrans": return $this->data->retrans;
            case "sent_bytes": return $this->data->sent_bytes;
            case "recv_bytes": return $this->data->recv_bytes;
            case "lost_bytes": return $this->data->lost_bytes;
            case "stream_retrans_bytes": return $this->data->stream_retrans_bytes;
            case "paths_count": return $this->data->paths_count;
            case "reset_stream_count_local": return $this->data->reset_stream_count_local;
            case "stopped_stream_count_local": return $this->data->stopped_stream_count_local;
            case "reset_stream_count_remote": return $this->data->reset_stream_count_remote;
            case "stopped_stream_count_remote": return $this->data->stopped_stream_count_remote;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function __set($prop, $value) {
        switch ($prop) {
            case "recv":
                $this->data->recv = $value;
                return;
            case "sent":
                $this->data->sent = $value;
                return;
            case "lost":
                $this->data->lost = $value;
                return;
            case "retrans":
                $this->data->retrans = $value;
                return;
            case "sent_bytes":
                $this->data->sent_bytes = $value;
                return;
            case "recv_bytes":
                $this->data->recv_bytes = $value;
                return;
            case "lost_bytes":
                $this->data->lost_bytes = $value;
                return;
            case "stream_retrans_bytes":
                $this->data->stream_retrans_bytes = $value;
                return;
            case "paths_count":
                $this->data->paths_count = $value;
                return;
            case "reset_stream_count_local":
                $this->data->reset_stream_count_local = $value;
                return;
            case "stopped_stream_count_local":
                $this->data->stopped_stream_count_local = $value;
                return;
            case "reset_stream_count_remote":
                $this->data->reset_stream_count_remote = $value;
                return;
            case "stopped_stream_count_remote":
                $this->data->stopped_stream_count_remote = $value;
                return;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function set(quiche_stats $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'quiche_stats'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
/**
 * @property struct_sockaddr_storage $local_addr
 * @property int $local_addr_len
 * @property struct_sockaddr_storage $peer_addr
 * @property int $peer_addr_len
 * @property int $validation_state
 * @property int $active
 * @property int $recv
 * @property int $sent
 * @property int $lost
 * @property int $retrans
 * @property int $rtt
 * @property int $cwnd
 * @property int $sent_bytes
 * @property int $recv_bytes
 * @property int $lost_bytes
 * @property int $stream_retrans_bytes
 * @property int $pmtu
 * @property int $delivery_rate
 */
class quiche_path_stats implements iQuiche {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(quiche_path_stats $other): bool { return $this->data == $other->data; }
    public function addr(): quiche_path_stats_ptr { return new quiche_path_stats_ptr(FFI::addr($this->data)); }
    public function __get($prop) {
        switch ($prop) {
            case "local_addr": return new struct_sockaddr_storage($this->data->local_addr);
            case "local_addr_len": return $this->data->local_addr_len;
            case "peer_addr": return new struct_sockaddr_storage($this->data->peer_addr);
            case "peer_addr_len": return $this->data->peer_addr_len;
            case "validation_state": return $this->data->validation_state;
            case "active": return $this->data->active;
            case "recv": return $this->data->recv;
            case "sent": return $this->data->sent;
            case "lost": return $this->data->lost;
            case "retrans": return $this->data->retrans;
            case "rtt": return $this->data->rtt;
            case "cwnd": return $this->data->cwnd;
            case "sent_bytes": return $this->data->sent_bytes;
            case "recv_bytes": return $this->data->recv_bytes;
            case "lost_bytes": return $this->data->lost_bytes;
            case "stream_retrans_bytes": return $this->data->stream_retrans_bytes;
            case "pmtu": return $this->data->pmtu;
            case "delivery_rate": return $this->data->delivery_rate;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function __set($prop, $value) {
        switch ($prop) {
            case "local_addr":
                (new struct_sockaddr_storage($_ = &$this->data->local_addr))->set($value);
                return;
            case "local_addr_len":
                $this->data->local_addr_len = $value;
                return;
            case "peer_addr":
                (new struct_sockaddr_storage($_ = &$this->data->peer_addr))->set($value);
                return;
            case "peer_addr_len":
                $this->data->peer_addr_len = $value;
                return;
            case "validation_state":
                $this->data->validation_state = $value;
                return;
            case "active":
                $this->data->active = $value;
                return;
            case "recv":
                $this->data->recv = $value;
                return;
            case "sent":
                $this->data->sent = $value;
                return;
            case "lost":
                $this->data->lost = $value;
                return;
            case "retrans":
                $this->data->retrans = $value;
                return;
            case "rtt":
                $this->data->rtt = $value;
                return;
            case "cwnd":
                $this->data->cwnd = $value;
                return;
            case "sent_bytes":
                $this->data->sent_bytes = $value;
                return;
            case "recv_bytes":
                $this->data->recv_bytes = $value;
                return;
            case "lost_bytes":
                $this->data->lost_bytes = $value;
                return;
            case "stream_retrans_bytes":
                $this->data->stream_retrans_bytes = $value;
                return;
            case "pmtu":
                $this->data->pmtu = $value;
                return;
            case "delivery_rate":
                $this->data->delivery_rate = $value;
                return;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function set(quiche_path_stats $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'quiche_path_stats'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
/**
 * @property int $s_addr
 */
class struct_in_addr implements iQuiche {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(struct_in_addr $other): bool { return $this->data == $other->data; }
    public function addr(): struct_in_addr_ptr { return new struct_in_addr_ptr(FFI::addr($this->data)); }
    public function __get($prop) {
        switch ($prop) {
            case "s_addr": return $this->data->s_addr;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function __set($prop, $value) {
        switch ($prop) {
            case "s_addr":
                $this->data->s_addr = $value;
                return;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function set(struct_in_addr $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'struct in_addr'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
/**
 * @property int $sin_len
 * @property int $sin_family
 * @property int $sin_port
 * @property struct_in_addr $sin_addr
 * @property string_ $sin_zero
 */
class struct_sockaddr_in implements iQuiche {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(struct_sockaddr_in $other): bool { return $this->data == $other->data; }
    public function addr(): struct_sockaddr_in_ptr { return new struct_sockaddr_in_ptr(FFI::addr($this->data)); }
    public function __get($prop) {
        switch ($prop) {
            case "sin_len": return $this->data->sin_len;
            case "sin_family": return $this->data->sin_family;
            case "sin_port": return $this->data->sin_port;
            case "sin_addr": return new struct_in_addr($this->data->sin_addr);
            case "sin_zero": return new string_($this->data->sin_zero);
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function __set($prop, $value) {
        switch ($prop) {
            case "sin_len":
                $this->data->sin_len = $value;
                return;
            case "sin_family":
                $this->data->sin_family = $value;
                return;
            case "sin_port":
                $this->data->sin_port = $value;
                return;
            case "sin_addr":
                (new struct_in_addr($_ = &$this->data->sin_addr))->set($value);
                return;
            case "sin_zero":
                (new string_($_ = &$this->data->sin_zero))->set($value);
                return;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function set(struct_sockaddr_in $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'struct sockaddr_in'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
/**
 * @property int $sin_len
 * @property int $sin_family
 * @property int $sin_port
 * @property struct_in_addr $sin_addr
 * @property string_ $sin_zero
 */
class struct_sockaddr_in_ptr implements iQuiche, iQuiche_ptr, \ArrayAccess {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(struct_sockaddr_in_ptr $other): bool { return $this->data == $other->data; }
    public function addr(): struct_sockaddr_in_ptr_ptr { return new struct_sockaddr_in_ptr_ptr(FFI::addr($this->data)); }
    public function deref(int $n = 0): struct_sockaddr_in { return new struct_sockaddr_in($this->data[$n]); }
    #[\ReturnTypeWillChange] public function offsetGet($offset): struct_sockaddr_in { return $this->deref($offset); }
    #[\ReturnTypeWillChange] public function offsetExists($offset): bool { return !FFI::isNull($this->data); }
    #[\ReturnTypeWillChange] public function offsetUnset($offset): void { throw new \Error("Cannot unset C structures"); }
    #[\ReturnTypeWillChange] public function offsetSet($offset, $value): void { $this->data[$offset] = $value->getData(); }
    public static function array(int $size = 1): self { return QuicheFFI::makeArray(self::class, $size); }
    /** @return struct_sockaddr_in[] */ public function toArray(int $length): array { $ret = []; for ($i = 0; $i < $length; ++$i) { $ret[] = new struct_sockaddr_in($this->data[$i]); } return $ret; }
    public function __get($prop) {
        switch ($prop) {
            case "sin_len": return $this->data[0]->sin_len;
            case "sin_family": return $this->data[0]->sin_family;
            case "sin_port": return $this->data[0]->sin_port;
            case "sin_addr": return new struct_in_addr($this->data[0]->sin_addr);
            case "sin_zero": return new string_($this->data[0]->sin_zero);
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function __set($prop, $value) {
        switch ($prop) {
            case "sin_len":
                $this->data[0]->sin_len = $value;
                return;
            case "sin_family":
                $this->data[0]->sin_family = $value;
                return;
            case "sin_port":
                $this->data[0]->sin_port = $value;
                return;
            case "sin_addr":
                (new struct_in_addr($_ = &$this->data[0]->sin_addr))->set($value);
                return;
            case "sin_zero":
                (new string_($_ = &$this->data[0]->sin_zero))->set($value);
                return;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function set(void_ptr | struct_sockaddr_in_ptr $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'struct sockaddr_in*'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
/**
 * @property union_anonymous_id_58 $__u6_addr
 */
class struct_in6_addr implements iQuiche {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(struct_in6_addr $other): bool { return $this->data == $other->data; }
    public function addr(): struct_in6_addr_ptr { return new struct_in6_addr_ptr(FFI::addr($this->data)); }
    public function __get($prop) {
        switch ($prop) {
            case "__u6_addr": return new union_anonymous_id_58($this->data->__u6_addr);
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function __set($prop, $value) {
        switch ($prop) {
            case "__u6_addr":
                (new union_anonymous_id_58($_ = &$this->data->__u6_addr))->set($value);
                return;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function set(struct_in6_addr $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'struct in6_addr'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
/**
 * @property union_anonymous_id_58 $__u6_addr
 */
class struct_in6_addr_ptr implements iQuiche, iQuiche_ptr, \ArrayAccess {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(struct_in6_addr_ptr $other): bool { return $this->data == $other->data; }
    public function addr(): struct_in6_addr_ptr_ptr { return new struct_in6_addr_ptr_ptr(FFI::addr($this->data)); }
    public function deref(int $n = 0): struct_in6_addr { return new struct_in6_addr($this->data[$n]); }
    #[\ReturnTypeWillChange] public function offsetGet($offset): struct_in6_addr { return $this->deref($offset); }
    #[\ReturnTypeWillChange] public function offsetExists($offset): bool { return !FFI::isNull($this->data); }
    #[\ReturnTypeWillChange] public function offsetUnset($offset): void { throw new \Error("Cannot unset C structures"); }
    #[\ReturnTypeWillChange] public function offsetSet($offset, $value): void { $this->data[$offset] = $value->getData(); }
    public static function array(int $size = 1): self { return QuicheFFI::makeArray(self::class, $size); }
    /** @return struct_in6_addr[] */ public function toArray(int $length): array { $ret = []; for ($i = 0; $i < $length; ++$i) { $ret[] = new struct_in6_addr($this->data[$i]); } return $ret; }
    public function __get($prop) {
        switch ($prop) {
            case "__u6_addr": return new union_anonymous_id_58($this->data[0]->__u6_addr);
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function __set($prop, $value) {
        switch ($prop) {
            case "__u6_addr":
                (new union_anonymous_id_58($_ = &$this->data[0]->__u6_addr))->set($value);
                return;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function set(void_ptr | struct_in6_addr_ptr $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'struct in6_addr*'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
/**
 * @property int $sin6_len
 * @property int $sin6_family
 * @property int $sin6_port
 * @property int $sin6_flowinfo
 * @property struct_in6_addr $sin6_addr
 * @property int $sin6_scope_id
 */
class struct_sockaddr_in6 implements iQuiche {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(struct_sockaddr_in6 $other): bool { return $this->data == $other->data; }
    public function addr(): struct_sockaddr_in6_ptr { return new struct_sockaddr_in6_ptr(FFI::addr($this->data)); }
    public function __get($prop) {
        switch ($prop) {
            case "sin6_len": return $this->data->sin6_len;
            case "sin6_family": return $this->data->sin6_family;
            case "sin6_port": return $this->data->sin6_port;
            case "sin6_flowinfo": return $this->data->sin6_flowinfo;
            case "sin6_addr": return new struct_in6_addr($this->data->sin6_addr);
            case "sin6_scope_id": return $this->data->sin6_scope_id;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function __set($prop, $value) {
        switch ($prop) {
            case "sin6_len":
                $this->data->sin6_len = $value;
                return;
            case "sin6_family":
                $this->data->sin6_family = $value;
                return;
            case "sin6_port":
                $this->data->sin6_port = $value;
                return;
            case "sin6_flowinfo":
                $this->data->sin6_flowinfo = $value;
                return;
            case "sin6_addr":
                (new struct_in6_addr($_ = &$this->data->sin6_addr))->set($value);
                return;
            case "sin6_scope_id":
                $this->data->sin6_scope_id = $value;
                return;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function set(struct_sockaddr_in6 $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'struct sockaddr_in6'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
/**
 * @property int $sin6_len
 * @property int $sin6_family
 * @property int $sin6_port
 * @property int $sin6_flowinfo
 * @property struct_in6_addr $sin6_addr
 * @property int $sin6_scope_id
 */
class struct_sockaddr_in6_ptr implements iQuiche, iQuiche_ptr, \ArrayAccess {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(struct_sockaddr_in6_ptr $other): bool { return $this->data == $other->data; }
    public function addr(): struct_sockaddr_in6_ptr_ptr { return new struct_sockaddr_in6_ptr_ptr(FFI::addr($this->data)); }
    public function deref(int $n = 0): struct_sockaddr_in6 { return new struct_sockaddr_in6($this->data[$n]); }
    #[\ReturnTypeWillChange] public function offsetGet($offset): struct_sockaddr_in6 { return $this->deref($offset); }
    #[\ReturnTypeWillChange] public function offsetExists($offset): bool { return !FFI::isNull($this->data); }
    #[\ReturnTypeWillChange] public function offsetUnset($offset): void { throw new \Error("Cannot unset C structures"); }
    #[\ReturnTypeWillChange] public function offsetSet($offset, $value): void { $this->data[$offset] = $value->getData(); }
    public static function array(int $size = 1): self { return QuicheFFI::makeArray(self::class, $size); }
    /** @return struct_sockaddr_in6[] */ public function toArray(int $length): array { $ret = []; for ($i = 0; $i < $length; ++$i) { $ret[] = new struct_sockaddr_in6($this->data[$i]); } return $ret; }
    public function __get($prop) {
        switch ($prop) {
            case "sin6_len": return $this->data[0]->sin6_len;
            case "sin6_family": return $this->data[0]->sin6_family;
            case "sin6_port": return $this->data[0]->sin6_port;
            case "sin6_flowinfo": return $this->data[0]->sin6_flowinfo;
            case "sin6_addr": return new struct_in6_addr($this->data[0]->sin6_addr);
            case "sin6_scope_id": return $this->data[0]->sin6_scope_id;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function __set($prop, $value) {
        switch ($prop) {
            case "sin6_len":
                $this->data[0]->sin6_len = $value;
                return;
            case "sin6_family":
                $this->data[0]->sin6_family = $value;
                return;
            case "sin6_port":
                $this->data[0]->sin6_port = $value;
                return;
            case "sin6_flowinfo":
                $this->data[0]->sin6_flowinfo = $value;
                return;
            case "sin6_addr":
                (new struct_in6_addr($_ = &$this->data[0]->sin6_addr))->set($value);
                return;
            case "sin6_scope_id":
                $this->data[0]->sin6_scope_id = $value;
                return;
        }
        throw new \Error("Unknown field $prop on type " . self::getType());
    }
    public function set(void_ptr | struct_sockaddr_in6_ptr $value): void {
        FFI::addr($this->data)[0] = $value->getData();
    }
    public static function getType(): string { return 'struct sockaddr_in6*'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
class _Bool_ptr implements iQuiche, iQuiche_ptr, \ArrayAccess {
    private FFI\CData $data;
    public function __construct(FFI\CData $data) { $this->data = $data; }
    public static function castFrom(iQuiche $data): self { return QuicheFFI::cast($data, self::class); }
    public function getData(): FFI\CData { return $this->data; }
    public function equals(_Bool_ptr $other): bool { return $this->data == $other->data; }
    public function addr(): _Bool_ptr_ptr { return new _Bool_ptr_ptr(FFI::addr($this->data)); }
    public function deref(int $n = 0): int { return $this->data[$n]; }
    #[\ReturnTypeWillChange] public function offsetGet($offset): int { return $this->deref($offset); }
    #[\ReturnTypeWillChange] public function offsetExists($offset): bool { return !FFI::isNull($this->data); }
    #[\ReturnTypeWillChange] public function offsetUnset($offset): void { throw new \Error("Cannot unset C structures"); }
    #[\ReturnTypeWillChange] public function offsetSet($offset, $value): void { $this->data[$offset] = $value; }
    public static function array(int $size = 1): self { return QuicheFFI::makeArray(self::class, $size); }
    /** @return int[] */ public function toArray(int $length): array { $ret = []; for ($i = 0; $i < $length; ++$i) { $ret[] = ($this->data[$i]); } return $ret; }
    public function toString(?int $length = null): string { return $length === null ? FFI::string(FFI::cdef()->cast("char*", $this->data)) : FFI::string(FFI::cdef()->cast("char*", $this->data), $length); }
    public static function persistent(string $string): self { $str = new self(FFI::cdef()->new("unsigned char[" . \strlen($string) . "]", false)); FFI::memcpy($str->data, $string, \strlen($string)); return $str; }
    public static function owned(string $string): self { $str = new self(FFI::cdef()->new("unsigned char[" . \strlen($string) . "]", true)); FFI::memcpy($str->data, $string, \strlen($string)); return $str; }
    public static function persistentZero(string $string): self { return self::persistent("$string\0"); }
    public static function ownedZero(string $string): self { return self::owned("$string\0"); }
    public function set(int | void_ptr | _Bool_ptr $value): void {
        if (\is_scalar($value)) {
            $this->data[0] = $value;
        } else {
            FFI::addr($this->data)[0] = $value->getData();
        }
    }
    public static function getType(): string { return '_Bool*'; }
    public static function size(): int { return QuicheFFI::sizeof(self::class); }
    public function getDefinition(): string { return static::getType(); }
}
(function() { self::$staticFFI = \FFI::cdef(QuicheFFI::TYPES_DEF); self::$__arrayWeakMap = new \WeakMap; })->bindTo(null, QuicheFFI::class)();
\class_alias(_Bool_ptr::class, uint8_t_ptr::class);
\class_alias(_Bool_ptr::class, unsigned_char_ptr::class);
\class_alias(string_::class, __int8_t_ptr::class);
\class_alias(string_::class, __darwin_uuid_string_t::class);
\class_alias(string_::class, caddr_t::class);
\class_alias(struct_quiche_config_ptr::class, quiche_config_ptr::class);
\class_alias(struct_quiche_conn_ptr::class, quiche_conn_ptr::class);
\class_alias(struct_in6_addr::class, in6_addr_t::class);
\class_alias(struct_in6_addr_ptr::class, in6_addr_t_ptr::class);